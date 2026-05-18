<?php
// api.php — Sorteador de YouTube — API JSON

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function json_ok(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function valid_uuid(string $s): bool {
    return (bool)preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $s
    );
}

function gen_uuid_v4(): string {
    return sprintf(
        '%08x-%04x-4%03x-%04x-%012x',
        mt_rand(0, 0xffffffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff),
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffffffffffff)
    );
}

function extract_video_id(string $url): ?string {
    $url = trim($url);
    // v=VIDEO_ID
    if (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m)) return $m[1];
    // youtu.be/VIDEO_ID
    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#', $url, $m)) return $m[1];
    // /embed/VIDEO_ID
    if (preg_match('#/embed/([a-zA-Z0-9_-]{11})#', $url, $m)) return $m[1];
    // /shorts/VIDEO_ID (YouTube Shorts)
    if (preg_match('#/shorts/([a-zA-Z0-9_-]{11})#', $url, $m)) return $m[1];
    // Bare video ID (11 chars)
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) return $url;
    return null;
}

function notify_telegram(string $msg): void {
    $token   = 'REDACTED_TG_TOKEN';
    $chat_id = '124659252';
    @file_get_contents(
        "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($msg)
    );
}

$action = $_GET['action'] ?? '';

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('POST requerido.', 405);

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_err('JSON inválido.');

    // Aceptar tanto url (string, retrocompat) como video_urls (array)
    $raw_urls = $body['video_urls'] ?? (isset($body['url']) ? [$body['url']] : []);
    if (!is_array($raw_urls)) $raw_urls = [$raw_urls];
    $raw_urls = array_slice(array_filter(array_map('trim', $raw_urls)), 0, 5);
    if (empty($raw_urls)) json_err('Ingresá al menos una URL de YouTube.');

    $video_ids = [];
    foreach ($raw_urls as $u) {
        $vid = extract_video_id($u);
        if ($vid && !in_array($vid, $video_ids)) $video_ids[] = $vid;
    }
    if (empty($video_ids)) json_err('URL de YouTube inválida.');

    // Rate limiting: máx 10 sorteos/hora por IP
    $ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip_hash = hash('sha256', trim(explode(',', $ip)[0]));
    if (!rate_limit_ok($ip_hash, 10, 3600)) {
        json_err('Límite de sorteos alcanzado. Podés crear hasta 10 sorteos por hora.', 429);
    }

    // Validar opciones
    $num_winners    = (int)($body['num_winners']    ?? 1);
    $max_comments   = (int)($body['max_comments']   ?? 10000);
    $keyword        = trim($body['keyword']         ?? '');
    $max_per_user   = max(0, min(50, (int)($body['max_per_user'] ?? 1)));
    $include_replies = !empty($body['include_replies']);
    $min_likes      = max(0, (int)($body['min_likes'] ?? 0));

    if ($num_winners < 1 || $num_winners > 100) {
        json_err('La cantidad de ganadores debe ser entre 1 y 100.');
    }

    $valid_max = [1000, 5000, 10000, 50000, 0];
    if (!in_array($max_comments, $valid_max, true)) {
        json_err('Límite de comentarios inválido.');
    }

    if (mb_strlen($keyword) > 100) {
        json_err('La palabra clave no puede superar 100 caracteres.');
    }

    $date_from    = trim($body['date_from'] ?? '');
    $date_to      = trim($body['date_to'] ?? '');
    $exclude_users = array_slice(
        array_filter(array_map('trim', (array)($body['exclude_users'] ?? []))),
        0, 50
    );
    $num_backups  = max(0, min(20, (int)($body['num_backups'] ?? 0)));

    if ($date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        json_err('Formato de fecha inválido.');
    }
    if ($date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        json_err('Formato de fecha inválido.');
    }

    $options = [
        'num_winners'     => $num_winners,
        'max_comments'    => $max_comments,
        'keyword'         => $keyword,
        'max_per_user'    => $max_per_user,
        'include_replies' => $include_replies,
        'date_from'       => $date_from,
        'date_to'         => $date_to,
        'exclude_users'   => array_values($exclude_users),
        'num_backups'     => $num_backups,
        'min_likes'       => $min_likes,
    ];

    $id = gen_uuid_v4();
    create_sorteo($id, $video_ids[0], $options);
    update_sorteo($id, ['video_ids' => json_encode($video_ids), 'ip_hash' => $ip_hash]);

    // Auto-detectar dueño del canal para el primer video
    $channel_owner = '';
    $video_title   = '';
    $vi_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet&id={$video_ids[0]}&key=" . YT_API_KEY;
    $vi_resp = @file_get_contents($vi_url);
    if ($vi_resp !== false) {
        $vi_data = json_decode($vi_resp, true);
        $channel_owner = $vi_data['items'][0]['snippet']['channelTitle'] ?? '';
        $video_title   = $vi_data['items'][0]['snippet']['title'] ?? '';
    }
    if ($channel_owner !== '') {
        update_sorteo($id, ['channel_title' => $channel_owner]);
    }

    // Notificar al admin
    $n_videos = count($video_ids);
    notify_telegram("🎰 Nuevo sorteo creado\nCanal: {$channel_owner}\nVideo: {$video_title}" . ($n_videos > 1 ? " (+".($n_videos-1)." más)" : "") . "\nGanadores: {$num_winners}" . ($num_backups > 0 ? " + {$num_backups} suplentes" : ""));

    json_ok(['id' => $id, 'video_id' => $video_ids[0], 'channel_owner' => $channel_owner]);
}

// ── SORTEAR ───────────────────────────────────────────────────────────────────
if ($action === 'sortear') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('POST requerido.', 405);

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_err('JSON inválido.');

    $id = trim($body['id'] ?? '');
    if (!valid_uuid($id)) json_err('ID inválido.');

    $sorteo = get_sorteo($id);
    if (!$sorteo) json_err('Sorteo no encontrado.', 404);

    if (!in_array($sorteo['status'], ['ready', 'done'])) {
        json_err('El sorteo todavía no está listo. Esperá a que terminen de descargarse los comentarios.');
    }

    $options         = $sorteo['options'] ?? [];
    $num_winners     = (int)($options['num_winners']  ?? 1);
    $keyword         = $options['keyword']       ?? '';
    // Compatibilidad hacia atrás: si existe max_per_user usarlo, si no derivar de unique_users
    $max_per_user    = isset($options['max_per_user']) ? (int)$options['max_per_user'] : ($options['unique_users'] ? 1 : 0);
    $include_replies = !empty($options['include_replies']);
    $date_from       = $options['date_from']     ?? '';
    $date_to         = $options['date_to']       ?? '';
    $exclude_users   = (array)($options['exclude_users'] ?? []);
    $num_backups     = (int)($options['num_backups'] ?? 0);
    $min_likes       = (int)($options['min_likes'] ?? 0);

    $rowids   = get_eligible_rowids($id, $keyword, $max_per_user, $include_replies, $date_from, $date_to, $exclude_users, $min_likes);
    $eligible = count($rowids);

    if ($eligible === 0) {
        $msg = 'No hay comentarios elegibles';
        if ($keyword !== '') $msg .= " con la palabra clave \"$keyword\"";
        $msg .= '.';
        json_err($msg);
    }

    $total_needed = $num_winners + $num_backups;
    if ($eligible < $total_needed) {
        json_err(
            "Se necesitan $total_needed participantes ($num_winners ganadores + $num_backups suplentes) pero solo hay $eligible comentarios elegibles."
        );
    }

    shuffle($rowids);
    $winner_ids = array_slice($rowids, 0, $num_winners);
    $backup_ids = array_slice($rowids, $num_winners, $num_backups);
    save_winners($id, $winner_ids, $backup_ids);

    $all_winners = get_winners($id);
    json_ok([
        'winners'  => array_values(array_filter($all_winners, fn($w) => !$w['is_backup'])),
        'backups'  => array_values(array_filter($all_winners, fn($w) =>  $w['is_backup'])),
        'eligible' => $eligible,
    ]);
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($action === 'get') {
    $id = trim($_GET['id'] ?? '');
    if (!valid_uuid($id)) json_err('ID inválido.');

    $sorteo = get_sorteo($id);
    if (!$sorteo) json_err('Sorteo no encontrado.', 404);

    $result = [
        'id'                  => $sorteo['id'],
        'video_id'            => $sorteo['video_id'],
        'video_title'         => $sorteo['video_title'],
        'video_thumb'         => $sorteo['video_thumb'],
        'video_comment_count' => (int)$sorteo['video_comment_count'],
        'status'              => $sorteo['status'],
        'total_fetched'       => (int)$sorteo['total_fetched'],
        'options'             => $sorteo['options'],
    ];

    if ($sorteo['status'] === 'done') {
        $all_winners = get_winners($sorteo['id']);
        $result['winners'] = array_values(array_filter($all_winners, fn($w) => !$w['is_backup']));
        $result['backups']  = array_values(array_filter($all_winners, fn($w) =>  $w['is_backup']));
    }

    json_ok($result);
}

json_err('Acción inválida.', 400);
