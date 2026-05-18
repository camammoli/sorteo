<?php
// db.php — Sorteador de YouTube — SQLite + PDO

define('DB_PATH', __DIR__ . '/data/sorteo.db');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $is_new = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    if ($is_new) {
        _create_schema($pdo);
    }

    _cleanup_old($pdo);

    // Migración: agregar is_backup si no existe
    try { $pdo->exec("ALTER TABLE winners ADD COLUMN is_backup INTEGER DEFAULT 0"); } catch (\PDOException $e) {}

    return $pdo;
}

function _create_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sorteos (
            id                  TEXT PRIMARY KEY,
            video_id            TEXT,
            video_title         TEXT,
            video_thumb         TEXT,
            video_comment_count INTEGER,
            options             TEXT,
            status              TEXT DEFAULT 'pending',
            total_fetched       INTEGER DEFAULT 0,
            created_at          TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS comments (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            sorteo_id   TEXT,
            comment_id  TEXT,
            author      TEXT,
            author_id   TEXT,
            text        TEXT,
            published_at TEXT,
            is_reply    INTEGER DEFAULT 0
        );

        CREATE INDEX IF NOT EXISTS idx_comments_sorteo
            ON comments(sorteo_id);

        CREATE INDEX IF NOT EXISTS idx_comments_author
            ON comments(sorteo_id, author_id);


        CREATE TABLE IF NOT EXISTS winners (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            sorteo_id    TEXT,
            comment_rowid INTEGER,
            position     INTEGER,
            drawn_at     TEXT DEFAULT (datetime('now'))
        );
    ");
}

function _cleanup_old(PDO $pdo): void {
    // Find sorteos older than 7 days
    $old = $pdo->query(
        "SELECT id FROM sorteos WHERE created_at < datetime('now', '-7 days')"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($old as $id) {
        $pdo->prepare("DELETE FROM winners  WHERE sorteo_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM comments WHERE sorteo_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM sorteos  WHERE id = ?")->execute([$id]);
    }
}

// ── CRUD ─────────────────────────────────────────────────────────────────────

function create_sorteo(string $id, string $video_id, array $options): void {
    $pdo = get_db();
    $pdo->prepare(
        "INSERT INTO sorteos (id, video_id, options, status, created_at)
         VALUES (?, ?, ?, 'pending', datetime('now'))"
    )->execute([$id, $video_id, json_encode($options)]);
}

function update_sorteo(string $id, array $data): void {
    if (empty($data)) return;
    $pdo = get_db();
    $sets = [];
    $vals = [];
    foreach ($data as $k => $v) {
        $sets[] = "$k = ?";
        $vals[] = $v;
    }
    $vals[] = $id;
    $pdo->prepare("UPDATE sorteos SET " . implode(', ', $sets) . " WHERE id = ?")
        ->execute($vals);
}

function get_sorteo(string $id): ?array {
    $pdo = get_db();
    $row = $pdo->prepare("SELECT * FROM sorteos WHERE id = ?");
    $row->execute([$id]);
    $data = $row->fetch();
    if (!$data) return null;
    if ($data['options']) {
        $data['options'] = json_decode($data['options'], true);
    }
    return $data;
}

function insert_comments_batch(string $sorteo_id, array $batch): void {
    if (empty($batch)) return;
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "INSERT INTO comments (sorteo_id, comment_id, author, author_id, text, published_at, is_reply)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $pdo->beginTransaction();
    foreach ($batch as $c) {
        $stmt->execute([
            $sorteo_id,
            $c['comment_id'],
            $c['author'],
            $c['author_id'],
            mb_substr($c['text'], 0, 1000),
            $c['published_at'],
            $c['is_reply'] ? 1 : 0,
        ]);
    }
    $pdo->commit();
}

function get_eligible_rowids(
    string $sorteo_id,
    string $keyword,
    bool   $unique_users,
    bool   $include_replies,
    string $date_from = '',
    string $date_to = '',
    array  $exclude_users = []
): array {
    $pdo = get_db();

    $where = ["sorteo_id = ?"];
    $params = [$sorteo_id];

    if (!$include_replies) {
        $where[] = "is_reply = 0";
    }

    if ($keyword !== '') {
        $where[] = "LOWER(text) LIKE ?";
        $params[] = '%' . mb_strtolower($keyword) . '%';
    }

    if ($date_from !== '') {
        $where[] = "DATE(published_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to !== '') {
        $where[] = "DATE(published_at) <= ?";
        $params[] = $date_to;
    }

    if (count($exclude_users) > 0) {
        $placeholders = implode(',', array_fill(0, count($exclude_users), '?'));
        $where[] = "LOWER(author) NOT IN ($placeholders)";
        foreach ($exclude_users as $u) {
            $params[] = mb_strtolower($u);
        }
    }

    $where_sql = implode(' AND ', $where);

    if ($unique_users) {
        $sql = "SELECT MIN(id) AS id
                FROM comments
                WHERE $where_sql
                GROUP BY author_id";
    } else {
        $sql = "SELECT id FROM comments WHERE $where_sql";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function save_winners(string $sorteo_id, array $winner_rowids, array $backup_rowids = []): void {
    $pdo = get_db();
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM winners WHERE sorteo_id = ?")->execute([$sorteo_id]);
    $stmt = $pdo->prepare(
        "INSERT INTO winners (sorteo_id, comment_rowid, position, is_backup) VALUES (?, ?, ?, ?)"
    );
    foreach ($winner_rowids as $pos => $rowid) {
        $stmt->execute([$sorteo_id, $rowid, $pos + 1, 0]);
    }
    foreach ($backup_rowids as $pos => $rowid) {
        $stmt->execute([$sorteo_id, $rowid, $pos + 1, 1]);
    }
    $pdo->prepare("UPDATE sorteos SET status = 'done' WHERE id = ?")->execute([$sorteo_id]);
    $pdo->commit();
}

function get_winners(string $sorteo_id): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "SELECT w.position, w.is_backup, w.drawn_at, c.comment_id, c.author, c.author_id, c.text, c.is_reply
         FROM winners w
         JOIN comments c ON c.id = w.comment_rowid
         WHERE w.sorteo_id = ?
         ORDER BY w.is_backup ASC, w.position ASC"
    );
    $stmt->execute([$sorteo_id]);
    return $stmt->fetchAll();
}
