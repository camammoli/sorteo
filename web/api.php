<?php
// api.php — Sorteador de YouTube — API JSON

require_once __DIR__ . '/db.php';

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
    // Bare video ID (11 chars)
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) return $url;
    return null;
}

$action = $_GET['action'] ?? '';

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('POST requerido.', 405);

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_err('JSON inválido.');

    $raw_url = trim($body['url'] ?? '');
    if (!$raw_url) json_err('URL del video requerida.');

    $video_id = extract_video_id($raw_url);
    if (!$video_id) json_err('URL de YouTube inválida.');

    // Validar opciones
    $num_winners    = (int)($body['num_winners']    ?? 1);
    $max_comments   = (int)($body['max_comments']   ?? 10000);
    $keyword        = trim($body['keyword']         ?? '');
    $unique_users   = !empty($body['unique_users']);
    $include_replies = !empty($body['include_replies']);

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

    $options = [
        'num_winners'     => $num_winners,
        'max_comments'    => $max_comments,
        'keyword'         => $keyword,
        'unique_users'    => $unique_users,
        'include_replies' => $include_replies,
    ];

    $id = gen_uuid_v4();
    create_sorteo($id, $video_id, $options);

    json_ok(['id' => $id, 'video_id' => $video_id]);
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

    $options      = $sorteo['options'] ?? [];
    $num_winners  = (int)($options['num_winners']  ?? 1);
    $keyword      = $options['keyword']       ?? '';
    $unique_users = !empty($options['unique_users']);
    $include_replies = !empty($options['include_replies']);

    $rowids = get_eligible_rowids($id, $keyword, $unique_users, $include_replies);
    $eligible = count($rowids);

    if ($eligible === 0) {
        $msg = 'No hay comentarios elegibles';
        if ($keyword !== '') $msg .= " con la palabra clave \"$keyword\"";
        $msg .= '.';
        json_err($msg);
    }

    if ($num_winners > $eligible) {
        json_err(
            "Se pidieron $num_winners ganadores pero solo hay $eligible comentarios elegibles."
        );
    }

    shuffle($rowids);
    $selected = array_slice($rowids, 0, $num_winners);
    save_winners($id, $selected);

    $winners = get_winners($id);
    json_ok(['winners' => $winners, 'eligible' => $eligible]);
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
        $result['winners'] = get_winners($sorteo['id']);
    }

    json_ok($result);
}

json_err('Acción inválida.', 400);
