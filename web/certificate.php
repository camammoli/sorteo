<?php
// certificate.php — Certificado imprimible de sorteo

require_once __DIR__ . '/db.php';

$id = trim($_GET['v'] ?? '');
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id)) {
    http_response_code(400); die('ID inválido');
}
$sorteo = get_sorteo($id);
if (!$sorteo || $sorteo['status'] !== 'done') {
    http_response_code(404); die('Sorteo no encontrado o aún no finalizado');
}
$all_winners = get_winners($id);
$winners = array_values(array_filter($all_winners, fn($w) => !$w['is_backup']));
$backups  = array_values(array_filter($all_winners, fn($w) =>  $w['is_backup']));
$opts = $sorteo['options'] ?? [];

// Hash de verificación: MD5 de id + winner comment_ids concatenados
$hash_input  = $id . implode(',', array_column($winners, 'comment_id'));
$verify_hash = strtoupper(substr(md5($hash_input), 0, 16));

$video_id    = htmlspecialchars($sorteo['video_id'] ?? '', ENT_QUOTES);
$video_title = htmlspecialchars($sorteo['video_title'] ?? 'Sin título', ENT_QUOTES);
$drawn_at    = !empty($all_winners) ? ($all_winners[0]['drawn_at'] ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s');
$drawn_at_fmt = date('d/m/Y H:i', strtotime($drawn_at));

$num_winners_opt = (int)($opts['num_winners'] ?? count($winners));
$keyword         = $opts['keyword'] ?? '';
$date_from       = $opts['date_from'] ?? '';
$date_to         = $opts['date_to'] ?? '';
$unique_users    = !empty($opts['unique_users']);

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificado de Sorteo — <?= $video_title ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: Georgia, 'Times New Roman', serif;
    background: #f5f5f0;
    color: #1a1a1a;
    font-size: 15px;
    line-height: 1.6;
    padding: 24px 16px 48px;
}

.cert-wrap {
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #d4b896;
    border-radius: 4px;
    box-shadow: 0 2px 12px rgba(0,0,0,.12);
    overflow: hidden;
}

/* Header */
.cert-header {
    background: #fff;
    border-bottom: 3px solid #b8860b;
    padding: 32px 40px 24px;
    text-align: center;
}
.cert-header .cert-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 10px;
}
.cert-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #7a5c00;
    letter-spacing: .03em;
    margin-bottom: 4px;
}
.cert-header .cert-subtitle {
    font-size: 13px;
    color: #888;
    font-style: italic;
}

/* Body */
.cert-body {
    padding: 32px 40px;
}

/* Sections */
.cert-section {
    margin-bottom: 28px;
}
.cert-section-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: #b8860b;
    border-bottom: 1px solid #e8d5a3;
    padding-bottom: 6px;
    margin-bottom: 14px;
}

/* Video info */
.cert-video-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 6px;
}
.cert-video-link {
    font-size: 13px;
    color: #1a6bb8;
    word-break: break-all;
    text-decoration: none;
}
.cert-video-link:hover { text-decoration: underline; }
.cert-video-id {
    font-size: 12px;
    color: #888;
    margin-top: 4px;
    font-family: 'Courier New', monospace;
}

/* Details table */
.cert-details {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 32px;
}
.cert-detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.cert-detail-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #999;
    font-family: Arial, sans-serif;
}
.cert-detail-value {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
}

/* Winners list */
.cert-winner-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.cert-winner-item {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 14px 16px;
    background: #fdfaf4;
    border: 1px solid #e8d5a3;
    border-radius: 4px;
}
.cert-winner-pos {
    font-size: 20px;
    font-weight: 900;
    color: #b8860b;
    min-width: 28px;
    text-align: center;
    flex-shrink: 0;
    line-height: 1.3;
    font-family: Arial, sans-serif;
}
.cert-winner-pos.pos-1 { color: #c8940f; }
.cert-winner-pos.pos-2 { color: #6b7280; }
.cert-winner-pos.pos-3 { color: #92400e; }
.cert-winner-body { flex: 1; min-width: 0; }
.cert-winner-author {
    font-size: 15px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 3px;
}
.cert-winner-comment {
    font-size: 13px;
    color: #555;
    font-style: italic;
    word-break: break-word;
    margin-bottom: 4px;
}
.cert-winner-link {
    font-size: 12px;
    color: #1a6bb8;
    text-decoration: none;
    font-family: Arial, sans-serif;
}
.cert-winner-link:hover { text-decoration: underline; }

/* Backups section */
.cert-backup-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.cert-backup-item {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 12px 16px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    opacity: .85;
}
.cert-backup-pos {
    font-size: 15px;
    font-weight: 700;
    color: #888;
    min-width: 28px;
    text-align: center;
    flex-shrink: 0;
    font-family: Arial, sans-serif;
}
.cert-backup-body { flex: 1; min-width: 0; }
.cert-backup-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #999;
    font-family: Arial, sans-serif;
    margin-bottom: 2px;
}
.cert-backup-author {
    font-size: 14px;
    font-weight: 600;
    color: #444;
}
.cert-backup-comment {
    font-size: 12px;
    color: #777;
    font-style: italic;
    word-break: break-word;
    margin-top: 2px;
}
.cert-backup-link {
    font-size: 12px;
    color: #1a6bb8;
    text-decoration: none;
    font-family: Arial, sans-serif;
}
.cert-backup-link:hover { text-decoration: underline; }

/* Footer */
.cert-footer {
    border-top: 2px solid #e8d5a3;
    padding: 18px 40px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #fdfaf4;
}
.cert-footer-hash {
    font-size: 13px;
    font-family: 'Courier New', monospace;
    color: #444;
    word-break: break-all;
}
.cert-footer-hash strong { color: #7a5c00; }
.cert-footer-meta {
    font-size: 11px;
    color: #999;
    font-family: Arial, sans-serif;
}
.cert-footer-meta a { color: #888; text-decoration: none; }
.cert-footer-meta a:hover { text-decoration: underline; }

/* Print button */
.no-print {
    text-align: center;
    margin-bottom: 20px;
}
.btn-print {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #7a5c00;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 11px 24px;
    font-size: 15px;
    font-weight: 600;
    font-family: Arial, sans-serif;
    cursor: pointer;
    text-decoration: none;
    transition: opacity .15s;
}
.btn-print:hover { opacity: .85; }

@media print {
    .no-print { display: none; }
    body { background: #fff; padding: 0; }
    .cert-wrap { box-shadow: none; border: 1px solid #ccc; }
}
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
</div>

<div class="cert-wrap">

    <div class="cert-header">
        <span class="cert-icon">🏆</span>
        <h1>Certificado de Sorteo</h1>
        <div class="cert-subtitle">Sorteo de comentarios de YouTube — resultado oficial</div>
    </div>

    <div class="cert-body">

        <!-- Video -->
        <div class="cert-section">
            <div class="cert-section-title">Video</div>
            <div class="cert-video-title"><?= $video_title ?></div>
            <a class="cert-video-link"
               href="https://www.youtube.com/watch?v=<?= $video_id ?>"
               target="_blank" rel="noopener">
                https://www.youtube.com/watch?v=<?= $video_id ?>
            </a>
            <div class="cert-video-id">ID: <?= $video_id ?></div>
        </div>

        <!-- Detalles del sorteo -->
        <div class="cert-section">
            <div class="cert-section-title">Detalles del sorteo</div>
            <div class="cert-details">
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Fecha y hora del sorteo</span>
                    <span class="cert-detail-value"><?= esc($drawn_at_fmt) ?></span>
                </div>
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Ganadores</span>
                    <span class="cert-detail-value"><?= count($winners) ?></span>
                </div>
                <?php if (!empty($backups)): ?>
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Suplentes</span>
                    <span class="cert-detail-value"><?= count($backups) ?></span>
                </div>
                <?php endif; ?>
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Usuario único</span>
                    <span class="cert-detail-value"><?= $unique_users ? 'Sí' : 'No' ?></span>
                </div>
                <?php if ($keyword !== ''): ?>
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Filtro palabra clave</span>
                    <span class="cert-detail-value"><?= esc($keyword) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($date_from !== ''): ?>
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Comentarios desde</span>
                    <span class="cert-detail-value"><?= esc($date_from) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($date_to !== ''): ?>
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Comentarios hasta</span>
                    <span class="cert-detail-value"><?= esc($date_to) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ganadores -->
        <div class="cert-section">
            <div class="cert-section-title">Ganadores</div>
            <ul class="cert-winner-list">
            <?php
            $medals    = ['🥇', '🥈', '🥉'];
            $posClasses = ['pos-1', 'pos-2', 'pos-3'];
            foreach ($winners as $i => $w):
                $pos      = (int)($w['position'] ?? ($i + 1));
                $posLabel = $medals[$pos - 1] ?? '#' . $pos;
                $posClass = $posClasses[$pos - 1] ?? '';
                $text     = $w['text'] ?? '';
                if (mb_strlen($text) > 200) $text = mb_substr($text, 0, 200) . '…';
                $base_cid = explode('.', $w['comment_id'] ?? '')[0];
                $yt_link  = 'https://www.youtube.com/watch?v=' . urlencode($sorteo['video_id']) .
                            '&lc=' . urlencode($base_cid);
            ?>
            <li class="cert-winner-item">
                <div class="cert-winner-pos <?= esc($posClass) ?>"><?= $posLabel ?></div>
                <div class="cert-winner-body">
                    <div class="cert-winner-author">@<?= esc($w['author'] ?? '') ?></div>
                    <?php if ($text): ?>
                    <div class="cert-winner-comment">"<?= esc($text) ?>"</div>
                    <?php endif; ?>
                    <a class="cert-winner-link" href="<?= esc($yt_link) ?>"
                       target="_blank" rel="noopener">Ver comentario en YouTube ↗</a>
                </div>
            </li>
            <?php endforeach; ?>
            </ul>
        </div>

        <?php if (!empty($backups)): ?>
        <!-- Suplentes -->
        <div class="cert-section">
            <div class="cert-section-title">Suplentes</div>
            <ul class="cert-backup-list">
            <?php foreach ($backups as $i => $w):
                $pos     = (int)($w['position'] ?? ($i + 1));
                $text    = $w['text'] ?? '';
                if (mb_strlen($text) > 200) $text = mb_substr($text, 0, 200) . '…';
                $base_cid = explode('.', $w['comment_id'] ?? '')[0];
                $yt_link  = 'https://www.youtube.com/watch?v=' . urlencode($sorteo['video_id']) .
                            '&lc=' . urlencode($base_cid);
            ?>
            <li class="cert-backup-item">
                <div class="cert-backup-pos"><?= $pos ?></div>
                <div class="cert-backup-body">
                    <div class="cert-backup-label">Suplente</div>
                    <div class="cert-backup-author">@<?= esc($w['author'] ?? '') ?></div>
                    <?php if ($text): ?>
                    <div class="cert-backup-comment">"<?= esc($text) ?>"</div>
                    <?php endif; ?>
                    <a class="cert-backup-link" href="<?= esc($yt_link) ?>"
                       target="_blank" rel="noopener">Ver comentario ↗</a>
                </div>
            </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </div><!-- /cert-body -->

    <div class="cert-footer">
        <div class="cert-footer-hash">
            Verificación: <strong><?= esc($verify_hash) ?></strong>
        </div>
        <div class="cert-footer-meta">
            Generado por <a href="https://mammoli.ar/sorteo/" target="_blank">mammoli.ar/sorteo</a>
            &nbsp;·&nbsp; ID: <?= esc($id) ?>
        </div>
    </div>

</div><!-- /cert-wrap -->

</body>
</html>
