<?php
// fetch.php — Sorteador de YouTube — SSE endpoint para descarga de comentarios

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// ── SSE headers ───────────────────────────────────────────────────────────────
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
set_time_limit(0);

function sse(array $data): void {
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

// ── Validar id ────────────────────────────────────────────────────────────────
$id = trim($_GET['id'] ?? '');
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id)) {
    sse(['type' => 'error', 'msg' => 'ID inválido.']);
    exit;
}

$sorteo = get_sorteo($id);
if (!$sorteo) {
    sse(['type' => 'error', 'msg' => 'Sorteo no encontrado.']);
    exit;
}

// Si ya está procesado, devolver estado
if (in_array($sorteo['status'], ['fetching', 'ready', 'done'])) {
    sse([
        'type'  => 'already_done',
        'total' => (int)$sorteo['total_fetched'],
        'status' => $sorteo['status'],
    ]);
    exit;
}

// ── Opciones ──────────────────────────────────────────────────────────────────
$options        = $sorteo['options'] ?? [];
$max_comments   = (int)($options['max_comments'] ?? 10000);
$include_replies = !empty($options['include_replies']);
if ($max_comments <= 0) $max_comments = 999999;

// Leer video IDs (multi-video)
$video_ids_arr = ($sorteo['video_ids'] ?? '')
    ? json_decode($sorteo['video_ids'], true)
    : [$sorteo['video_id']];
$video_ids_arr = array_filter(array_unique((array)$video_ids_arr));
$total_videos  = count($video_ids_arr);

update_sorteo($id, ['status' => 'fetching']);

// ── Loop por cada video ────────────────────────────────────────────────────────
$total          = 0;
$first_title    = '';
$first_thumb    = '';
$first_cc       = null;

foreach ($video_ids_arr as $vid_idx => $video_id) {
    if (connection_aborted()) break;

    sse(['type' => 'video_start', 'index' => $vid_idx, 'total' => $total_videos]);

    // ── 1. Obtener info del video ─────────────────────────────────────────────
    $video_url = "https://www.googleapis.com/youtube/v3/videos"
        . "?part=snippet,statistics"
        . "&id=" . urlencode($video_id)
        . "&key=" . urlencode(YT_API_KEY);

    $video_resp = @file_get_contents($video_url);
    if ($video_resp === false) {
        update_sorteo($id, ['status' => 'pending']);
        sse(['type' => 'error', 'msg' => 'No se pudo conectar con la API de YouTube.']);
        exit;
    }

    $video_data = json_decode($video_resp, true);

    if (empty($video_data['items'])) {
        update_sorteo($id, ['status' => 'pending']);
        sse(['type' => 'error', 'msg' => 'Video no encontrado o no disponible.']);
        exit;
    }

    $video_item   = $video_data['items'][0];
    $snippet      = $video_item['snippet'];
    $stats        = $video_item['statistics'] ?? [];
    $title        = $snippet['title'] ?? 'Sin título';
    $thumb        = $snippet['thumbnails']['medium']['url']
                 ?? $snippet['thumbnails']['default']['url']
                 ?? '';
    $comment_count = isset($stats['commentCount']) ? (int)$stats['commentCount'] : null;

    if ($comment_count === null) {
        update_sorteo($id, ['status' => 'pending']);
        sse(['type' => 'error', 'msg' => 'Este video tiene los comentarios desactivados.']);
        exit;
    }

    if ($vid_idx === 0) {
        $first_title = $title;
        $first_thumb = $thumb;
        $first_cc    = $comment_count;
        update_sorteo($id, [
            'video_title'         => $title,
            'video_thumb'         => $thumb,
            'video_comment_count' => $comment_count,
        ]);
    }

    $max = min($max_comments, $comment_count);

    sse([
        'type'          => 'info',
        'title'         => $title,
        'thumb'         => $thumb,
        'comment_count' => $comment_count,
        'max'           => $max,
    ]);

    // ── 2. Paginar comentarios ────────────────────────────────────────────────
    $page_token  = '';
    $batch       = [];
    $batch_limit = 500;
    $video_total = 0; // contador por video (no global) para respetar el límite individualmente

    $parts = 'snippet';
    if ($include_replies) $parts .= ',replies';

    while (true) {
        if (connection_aborted()) break;

        $url = "https://www.googleapis.com/youtube/v3/commentThreads"
            . "?part=" . urlencode($parts)
            . "&videoId=" . urlencode($video_id)
            . "&maxResults=100"
            . "&textFormat=plainText"
            . "&key=" . urlencode(YT_API_KEY);
        if ($page_token !== '') {
            $url .= "&pageToken=" . urlencode($page_token);
        }

        $resp = @file_get_contents($url);
        if ($resp === false) {
            // Flush lo que tengamos antes de reportar error
            if (!empty($batch)) {
                insert_comments_batch($id, $batch);
                $total += count($batch);
                $batch = [];
            }
            update_sorteo($id, ['status' => 'pending', 'total_fetched' => $total]);
            sse(['type' => 'error', 'msg' => 'Error de red al contactar YouTube.']);
            exit;
        }

        $data = json_decode($resp, true);

        // Manejo de errores de API
        if (!empty($data['error'])) {
            $errors  = $data['error']['errors'] ?? [];
            $reason  = $errors[0]['reason'] ?? '';
            if ($reason === 'quotaExceeded' || $reason === 'dailyLimitExceeded') {
                update_sorteo($id, ['status' => 'pending']);
                sse(['type' => 'error', 'msg' => 'Se agotó la cuota diaria de la API de YouTube. Intentá mañana.']);
            } elseif ($reason === 'commentsDisabled') {
                update_sorteo($id, ['status' => 'pending']);
                sse(['type' => 'error', 'msg' => 'Los comentarios de este video están desactivados.']);
            } else {
                update_sorteo($id, ['status' => 'pending']);
                sse(['type' => 'error', 'msg' => 'Error de API: ' . ($data['error']['message'] ?? 'desconocido')]);
            }
            exit;
        }

        $items = $data['items'] ?? [];

        foreach ($items as $thread) {
            // Comentario principal
            $top = $thread['snippet']['topLevelComment']['snippet'] ?? null;
            if ($top) {
                $batch[] = [
                    'comment_id'      => $thread['snippet']['topLevelComment']['id'],
                    'author'          => $top['authorDisplayName'] ?? 'Anónimo',
                    'author_id'       => $top['authorChannelId']['value'] ?? '',
                    'text'            => $top['textDisplay'] ?? '',
                    'published_at'    => $top['publishedAt'] ?? '',
                    'is_reply'        => 0,
                    'like_count'      => (int)($top['likeCount'] ?? 0),
                    'source_video_id' => $video_id,
                ];
            }

            // Respuestas inline (hasta 5)
            if ($include_replies) {
                $replies = $thread['replies']['comments'] ?? [];
                foreach ($replies as $reply) {
                    $rs = $reply['snippet'] ?? null;
                    if (!$rs) continue;
                    $batch[] = [
                        'comment_id'      => $reply['id'],
                        'author'          => $rs['authorDisplayName'] ?? 'Anónimo',
                        'author_id'       => $rs['authorChannelId']['value'] ?? '',
                        'text'            => $rs['textDisplay'] ?? '',
                        'published_at'    => $rs['publishedAt'] ?? '',
                        'is_reply'        => 1,
                        'like_count'      => (int)($rs['likeCount'] ?? 0),
                        'source_video_id' => $video_id,
                    ];
                }
            }

            // Verificar límite
            if ($video_total + count($batch) >= $max) break;
        }

        // Flush batch cada 500
        if (count($batch) >= $batch_limit) {
            insert_comments_batch($id, $batch);
            $cnt = count($batch);
            $video_total += $cnt;
            $total       += $cnt;
            $batch = [];
            sse(['type' => 'progress', 'loaded' => $total]);
        }

        // Límite alcanzado
        if ($video_total + count($batch) >= $max) break;

        // Siguiente página
        $page_token = $data['nextPageToken'] ?? '';
        if ($page_token === '') break;
    }

    // Insertar lo que queda de este video
    if (!empty($batch)) {
        insert_comments_batch($id, $batch);
        $total += count($batch);
        $batch  = [];
    }
} // fin foreach videos

// Si hay más de un video, actualizar el título con "X videos"
if ($total_videos > 1) {
    update_sorteo($id, ['video_title' => $first_title . ' (y ' . ($total_videos - 1) . ' más)']);
}

update_sorteo($id, [
    'status'        => 'ready',
    'total_fetched' => $total,
]);

sse(['type' => 'done', 'total' => $total]);
