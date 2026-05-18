<?php
// verificar.php — Verifica la autenticidad de un certificado de sorteo

require_once __DIR__ . '/db.php';

$id   = trim($_GET['v'] ?? '');
$hash = strtoupper(trim($_GET['h'] ?? ''));
$lang = (trim($_GET['lang'] ?? 'es') === 'en') ? 'en' : 'es';

$valid_uuid = (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id);
$valid_hash = (bool)preg_match('/^[0-9A-F]{16}$/', $hash);

$state        = 'form';
$sorteo       = null;
$winners      = [];
$drawn_at_fmt = '';
$video_title  = '';
$video_id_raw = '';

if ($id !== '' || $hash !== '') {
    if (!$valid_uuid || !$valid_hash) {
        $state = 'invalid';
    } else {
        $sorteo = get_sorteo($id);
        if (!$sorteo || $sorteo['status'] !== 'done') {
            $state = 'not_found';
        } else {
            $all     = get_winners($id);
            $winners = array_values(array_filter($all, fn($w) => !$w['is_backup']));
            $expected = strtoupper(substr(md5($id . implode(',', array_column($winners, 'comment_id'))), 0, 16));
            if ($expected === $hash) {
                $state        = 'ok';
                $video_title  = $sorteo['video_title'] ?? '';
                $video_id_raw = $sorteo['video_id']    ?? '';
                $drawn_at_raw = !empty($all) ? ($all[0]['drawn_at'] ?? '') : '';
                $drawn_at_fmt = $drawn_at_raw ? date('d/m/Y H:i', strtotime($drawn_at_raw)) : '';
            } else {
                $state = 'tampered';
            }
        }
    }
}

$strings = [
    'es' => [
        'page_title'       => 'Verificar certificado — Sorteador de YouTube',
        'heading'          => 'Verificación de certificado',
        'subheading'       => 'Sorteador de YouTube · mammoli.ar',
        'ok_title'         => 'Certificado auténtico',
        'ok_desc'          => 'El certificado es válido y no fue modificado desde que se emitió.',
        'tampered_title'   => 'Certificado alterado',
        'tampered_desc'    => 'El contenido no coincide con el sorteo original. El documento fue modificado después de emitido.',
        'not_found_title'  => 'Sorteo no encontrado',
        'not_found_desc'   => 'Este sorteo ya no está disponible. Los resultados se conservan durante 7 días desde la fecha del sorteo.',
        'invalid_title'    => 'Código inválido',
        'invalid_desc'     => 'El código de verificación o el ID del sorteo no tienen el formato correcto.',
        'form_heading'     => 'Verificar un certificado',
        'form_desc'        => 'Escaneá el QR del certificado o ingresá el código de verificación manualmente.',
        'form_id_label'    => 'ID del sorteo',
        'form_hash_label'  => 'Código de verificación',
        'form_btn'         => 'Verificar',
        'form_id_ph'       => 'xxxxxxxx-xxxx-4xxx-xxxx-xxxxxxxxxxxx',
        'form_hash_ph'     => 'Ej: A1B2C3D4E5F60123',
        'video_label'      => 'Video',
        'date_label'       => 'Fecha del sorteo',
        'winners_label'    => 'Ganadores verificados',
        'hash_label'       => 'Código',
        'view_comment'     => 'Ver comentario en YouTube ↗',
        'back_link'        => '← Ir al Sorteador',
        'cert_link'        => '📄 Ver certificado completo',
    ],
    'en' => [
        'page_title'       => 'Verify certificate — YouTube Comment Picker',
        'heading'          => 'Certificate Verification',
        'subheading'       => 'YouTube Comment Picker · mammoli.ar',
        'ok_title'         => 'Authentic Certificate',
        'ok_desc'          => 'This certificate is valid and has not been modified since it was issued.',
        'tampered_title'   => 'Tampered Certificate',
        'tampered_desc'    => 'The content does not match the original draw. The document was modified after being issued.',
        'not_found_title'  => 'Draw Not Found',
        'not_found_desc'   => 'This draw is no longer available. Results are kept for 7 days from the draw date.',
        'invalid_title'    => 'Invalid Code',
        'invalid_desc'     => 'The verification code or draw ID are not in the correct format.',
        'form_heading'     => 'Verify a certificate',
        'form_desc'        => 'Scan the certificate QR or enter the verification code manually.',
        'form_id_label'    => 'Draw ID',
        'form_hash_label'  => 'Verification code',
        'form_btn'         => 'Verify',
        'form_id_ph'       => 'xxxxxxxx-xxxx-4xxx-xxxx-xxxxxxxxxxxx',
        'form_hash_ph'     => 'E.g.: A1B2C3D4E5F60123',
        'video_label'      => 'Video',
        'date_label'       => 'Draw date',
        'winners_label'    => 'Verified Winners',
        'hash_label'       => 'Code',
        'view_comment'     => 'View comment on YouTube ↗',
        'back_link'        => '← Go to Comment Picker',
        'cert_link'        => '📄 View full certificate',
    ],
];
$s = $strings[$lang];

function vs(string $key): string {
    global $s;
    return htmlspecialchars($s[$key] ?? $key, ENT_QUOTES, 'UTF-8');
}
function vesc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$state_colors = [
    'ok'        => ['bg' => '#f0fdf4', 'border' => '#86efac', 'icon_bg' => '#22c55e', 'icon' => '✓', 'text' => '#14532d'],
    'tampered'  => ['bg' => '#fef2f2', 'border' => '#fca5a5', 'icon_bg' => '#ef4444', 'icon' => '✗', 'text' => '#7f1d1d'],
    'not_found' => ['bg' => '#fffbeb', 'border' => '#fcd34d', 'icon_bg' => '#f59e0b', 'icon' => '⏳', 'text' => '#78350f'],
    'invalid'   => ['bg' => '#fff7ed', 'border' => '#fdba74', 'icon_bg' => '#f97316', 'icon' => '⚠', 'text' => '#7c2d12'],
];
$sc = $state_colors[$state] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= vs('page_title') ?></title>
<meta name="robots" content="noindex,nofollow">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #f1f5f9;
    color: #1e293b;
    min-height: 100vh;
    font-size: 15px;
    line-height: 1.5;
}

/* Header */
.v-header {
    background: #0f0f0f;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,.1);
}
.v-header-yt {
    display: flex; align-items: center; text-decoration: none;
}
.v-header-yt svg { width: 34px; height: 24px; }
.v-header-brand {
    flex: 1;
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -.2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.v-header-brand span {
    background: linear-gradient(135deg, #ef4444, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.v-header-sub {
    font-size: 11px;
    color: rgba(255,255,255,.45);
    white-space: nowrap;
}

/* Wrap */
.v-wrap {
    max-width: 560px;
    margin: 32px auto;
    padding: 0 16px 48px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Status card */
.v-status {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid;
}
.v-status-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 22px;
}
.v-status-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.v-status-title {
    font-size: 19px;
    font-weight: 800;
    line-height: 1.2;
}
.v-status-desc {
    font-size: 14px;
    opacity: .8;
    margin-top: 2px;
}

/* Info card */
.v-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px 22px;
}
.v-card-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 8px;
    margin-bottom: 14px;
}
.v-details {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 28px;
    margin-bottom: 14px;
}
.v-detail { display: flex; flex-direction: column; gap: 2px; }
.v-detail-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; font-family: Arial, sans-serif; }
.v-detail-value { font-size: 14px; font-weight: 600; color: #1e293b; }

/* Winners */
.v-winner-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.v-winner-item {
    display: flex;
    gap: 12px;
    padding: 10px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    align-items: flex-start;
}
.v-winner-pos {
    font-size: 18px;
    font-weight: 900;
    min-width: 28px;
    text-align: center;
    flex-shrink: 0;
    line-height: 1.4;
}
.v-winner-pos.p1 { color: #f59e0b; }
.v-winner-pos.p2 { color: #94a3b8; }
.v-winner-pos.p3 { color: #cd7f32; }
.v-winner-body { flex: 1; min-width: 0; }
.v-winner-author { font-weight: 700; font-size: 14px; color: #1e293b; }
.v-winner-text { font-size: 13px; color: #64748b; margin-top: 2px; word-break: break-word; font-style: italic; }
.v-winner-link { font-size: 12px; color: #2563eb; text-decoration: none; margin-top: 4px; display: inline-block; }
.v-winner-link:hover { text-decoration: underline; }

/* Hash */
.v-hash {
    margin-top: 12px;
    padding: 10px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    gap: 8px;
    align-items: baseline;
    flex-wrap: wrap;
}
.v-hash-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; flex-shrink: 0; }
.v-hash-value { font-family: 'Courier New', monospace; font-size: 14px; font-weight: 700; color: #1e293b; word-break: break-all; }

/* Form */
.v-form-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px 22px;
}
.v-form-heading { font-size: 19px; font-weight: 800; color: #1e293b; margin-bottom: 6px; }
.v-form-desc { font-size: 14px; color: #64748b; margin-bottom: 20px; }
.v-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.v-field label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
.v-field input {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    color: #1e293b;
    padding: 10px 12px;
    font-size: 14px;
    font-family: inherit;
    width: 100%;
    outline: none;
    transition: border-color .15s;
}
.v-field input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
.v-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 11px 24px;
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    width: 100%;
    transition: opacity .15s;
}
.v-btn:hover { opacity: .88; }

/* Actions */
.v-actions { display: flex; flex-direction: column; gap: 8px; }
.v-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #475569;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    text-decoration: none;
    text-align: center;
    transition: border-color .15s, color .15s;
}
.v-link:hover { border-color: #94a3b8; color: #1e293b; }
</style>
</head>
<body>

<header class="v-header">
    <a href="https://mammoli.ar/sorteo/" class="v-header-yt" aria-label="Sorteador de YouTube">
        <svg viewBox="0 0 90 63" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="0" y="0" width="90" height="63" rx="14" ry="14" fill="#FF0000"/>
            <polygon points="35,18 65,31.5 35,45" fill="#ffffff"/>
        </svg>
    </a>
    <div>
        <div class="v-header-brand">YouTube <span><?= $lang === 'es' ? 'Sorteador' : 'Comment Picker' ?></span></div>
        <div class="v-header-sub"><?= vs('heading') ?></div>
    </div>
</header>

<div class="v-wrap">

<?php if ($state === 'form'): ?>

    <div class="v-form-card">
        <div class="v-form-heading"><?= vs('form_heading') ?></div>
        <div class="v-form-desc"><?= vs('form_desc') ?></div>
        <form method="get" action="">
            <input type="hidden" name="lang" value="<?= vesc($lang) ?>">
            <div class="v-field">
                <label for="fi_v"><?= vs('form_id_label') ?></label>
                <input type="text" id="fi_v" name="v" placeholder="<?= vs('form_id_ph') ?>" spellcheck="false" autocomplete="off">
            </div>
            <div class="v-field">
                <label for="fi_h"><?= vs('form_hash_label') ?></label>
                <input type="text" id="fi_h" name="h" placeholder="<?= vs('form_hash_ph') ?>" spellcheck="false" autocomplete="off" style="font-family:'Courier New',monospace;text-transform:uppercase">
            </div>
            <button type="submit" class="v-btn"><?= vs('form_btn') ?></button>
        </form>
    </div>

<?php else: ?>

    <!-- Estado del certificado -->
    <div class="v-status" style="background:<?= $sc['bg'] ?>;border-color:<?= $sc['border'] ?>">
        <div class="v-status-header">
            <div class="v-status-icon" style="background:<?= $sc['icon_bg'] ?>"><?= $sc['icon'] ?></div>
            <div>
                <div class="v-status-title" style="color:<?= $sc['text'] ?>"><?= vs($state . '_title') ?></div>
                <div class="v-status-desc" style="color:<?= $sc['text'] ?>"><?= vs($state . '_desc') ?></div>
            </div>
        </div>
    </div>

    <?php if ($state === 'ok'): ?>

    <!-- Detalles del sorteo -->
    <div class="v-card">
        <div class="v-card-title"><?= vs('video_label') ?></div>
        <div class="v-details">
            <?php if ($video_title): ?>
            <div class="v-detail">
                <span class="v-detail-label"><?= vs('video_label') ?></span>
                <span class="v-detail-value"><?= vesc($video_title) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($drawn_at_fmt): ?>
            <div class="v-detail">
                <span class="v-detail-label"><?= vs('date_label') ?></span>
                <span class="v-detail-value"><?= vesc($drawn_at_fmt) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ganadores -->
    <div class="v-card">
        <div class="v-card-title"><?= vs('winners_label') ?></div>
        <ul class="v-winner-list">
        <?php
        $medals    = ['🥇','🥈','🥉'];
        $posClass  = ['p1','p2','p3'];
        foreach ($winners as $i => $w):
            $pos     = (int)($w['position'] ?? ($i + 1));
            $label   = $medals[$pos - 1] ?? '#' . $pos;
            $cls     = $posClass[$pos - 1] ?? '';
            $text    = $w['text'] ?? '';
            if (mb_strlen($text) > 160) $text = mb_substr($text, 0, 160) . '…';
            $base_cid = explode('.', $w['comment_id'] ?? '')[0];
            $yt_link  = 'https://www.youtube.com/watch?v=' . urlencode($video_id_raw)
                      . '&lc=' . urlencode($base_cid);
        ?>
        <li class="v-winner-item">
            <div class="v-winner-pos <?= vesc($cls) ?>"><?= $label ?></div>
            <div class="v-winner-body">
                <div class="v-winner-author">@<?= vesc($w['author'] ?? '') ?></div>
                <?php if ($text): ?><div class="v-winner-text">"<?= vesc($text) ?>"</div><?php endif; ?>
                <a class="v-winner-link" href="<?= vesc($yt_link) ?>" target="_blank" rel="noopener"><?= vs('view_comment') ?></a>
            </div>
        </li>
        <?php endforeach; ?>
        </ul>

        <div class="v-hash">
            <span class="v-hash-label"><?= vs('hash_label') ?></span>
            <span class="v-hash-value"><?= vesc($hash) ?></span>
        </div>
    </div>

    <?php endif; ?>

<?php endif; ?>

    <!-- Acciones -->
    <div class="v-actions">
        <?php if ($state === 'ok' && $id): ?>
        <a class="v-link" href="certificate.php?v=<?= vesc($id) ?>&amp;lang=<?= vesc($lang) ?>" target="_blank"><?= vs('cert_link') ?></a>
        <?php endif; ?>
        <a class="v-link" href="https://mammoli.ar/sorteo/"><?= vs('back_link') ?></a>
    </div>

</div><!-- /v-wrap -->

</body>
</html>
