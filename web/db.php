<?php
// db.php — Sorteador de YouTube — SQLite + PDO

// Zona horaria del visitante (detectada por JS en index.php y guardada en cookie)
$_sorteo_tz = $_COOKIE['sorteo_tz'] ?? 'America/Argentina/Mendoza';
if (!in_array($_sorteo_tz, timezone_identifiers_list())) {
    $_sorteo_tz = 'America/Argentina/Mendoza';
}
date_default_timezone_set($_sorteo_tz);

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
    // Migración: like_count en comments
    try { $pdo->exec("ALTER TABLE comments ADD COLUMN like_count INTEGER DEFAULT 0"); } catch (\PDOException $e) {}
    // Migración: source_video_id en comments
    try { $pdo->exec("ALTER TABLE comments ADD COLUMN source_video_id TEXT"); } catch (\PDOException $e) {}
    // Migración: video_ids en sorteos
    try { $pdo->exec("ALTER TABLE sorteos ADD COLUMN video_ids TEXT"); } catch (\PDOException $e) {}
    // Migración: channel_title y ip_hash en sorteos (stats + rate limit)
    try { $pdo->exec("ALTER TABLE sorteos ADD COLUMN channel_title TEXT"); } catch (\PDOException $e) {}
    try { $pdo->exec("ALTER TABLE sorteos ADD COLUMN ip_hash TEXT"); } catch (\PDOException $e) {}
    // Tabla de rate limiting por IP
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sorteo_rate (
            ip_hash     TEXT NOT NULL,
            window_start INTEGER NOT NULL,
            count       INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (ip_hash, window_start)
        )");
    } catch (\PDOException $e) {}

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
    // Paso 1 — +7 días: borrar solo comentarios de NO ganadores.
    // Los comentarios de ganadores se conservan para que la verificación funcione indefinidamente.
    $old_7 = $pdo->query(
        "SELECT id FROM sorteos WHERE created_at < datetime('now', '-7 days')"
    )->fetchAll(PDO::FETCH_COLUMN);

    $del_non_winners = $pdo->prepare(
        "DELETE FROM comments
         WHERE sorteo_id = ?
           AND id NOT IN (SELECT comment_rowid FROM winners WHERE sorteo_id = ?)"
    );
    foreach ($old_7 as $id) {
        $del_non_winners->execute([$id, $id]);
    }

    // Paso 2 — +1 año: borrado completo (ganadores, comentarios residuales, sorteo).
    $old_1y = $pdo->query(
        "SELECT id FROM sorteos WHERE created_at < datetime('now', '-365 days')"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($old_1y as $id) {
        $pdo->prepare("DELETE FROM winners  WHERE sorteo_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM comments WHERE sorteo_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM sorteos  WHERE id = ?")->execute([$id]);
    }

    // Limpieza rate limit: borrar ventanas de más de 2 horas
    try {
        $pdo->exec("DELETE FROM sorteo_rate WHERE window_start < " . (time() - 7200));
    } catch (\PDOException $e) {}
}

// ── RATE LIMITING ─────────────────────────────────────────────────────────────

function rate_limit_ok(string $ip_hash, int $max = 10, int $window = 3600): bool {
    $pdo = get_db();
    // Ventana alineada a la hora local (no UTC)
    $win = (int)strtotime(date('Y-m-d H:00:00'));
    $st = $pdo->prepare("SELECT count FROM sorteo_rate WHERE ip_hash = ? AND window_start = ?");
    $st->execute([$ip_hash, $win]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->prepare("INSERT INTO sorteo_rate (ip_hash, window_start, count) VALUES (?, ?, 1)")
            ->execute([$ip_hash, $win]);
        return true;
    }
    if ((int)$row['count'] >= $max) return false;
    $pdo->prepare("UPDATE sorteo_rate SET count = count + 1 WHERE ip_hash = ? AND window_start = ?")
        ->execute([$ip_hash, $win]);
    return true;
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
        "INSERT INTO comments (sorteo_id, comment_id, author, author_id, text, published_at, is_reply, like_count, source_video_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
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
            $c['like_count'] ?? 0,
            $c['source_video_id'] ?? null,
        ]);
    }
    $pdo->commit();
}

function get_eligible_rowids(
    string $sorteo_id,
    string $keyword,
    int    $max_per_user,
    bool   $include_replies,
    string $date_from = '',
    string $date_to = '',
    array  $exclude_users = [],
    int    $min_likes = 0
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

    if ($min_likes > 0) {
        $where[] = "like_count >= ?";
        $params[] = $min_likes;
    }

    $where_sql = implode(' AND ', $where);

    if ($max_per_user > 0) {
        // Traer todos los id+author_id del resultado filtrado (sin LIMIT)
        // ORDER BY id ASC para que "primer comentario" sea el contado
        $sql_fetch = "SELECT id, author_id FROM comments WHERE $where_sql ORDER BY id ASC";
        $st = $pdo->prepare($sql_fetch);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $counts = []; $result = [];
        foreach ($rows as $row) {
            $key = $row['author_id'] ?: ('__id_' . $row['id']);
            $n = ($counts[$key] ?? 0) + 1;
            $counts[$key] = $n;
            if ($n <= $max_per_user) $result[] = (int)$row['id'];
        }
        return $result;
    } else {
        $sql = "SELECT id FROM comments WHERE $where_sql";
        $st = $pdo->prepare($sql); $st->execute($params);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }
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
        "SELECT w.position, w.is_backup, w.drawn_at, c.comment_id, c.author, c.author_id, c.text, c.is_reply, c.source_video_id
         FROM winners w
         JOIN comments c ON c.id = w.comment_rowid
         WHERE w.sorteo_id = ?
         ORDER BY w.is_backup ASC, w.position ASC"
    );
    $stmt->execute([$sorteo_id]);
    return $stmt->fetchAll();
}
