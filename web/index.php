<?php
// index.php — Sorteador de YouTube

require_once __DIR__ . '/db.php';

$page_title = 'Sorteador de YouTube — Sorteos de comentarios sin apps';
$og_desc    = 'Sorteá ganadores entre los comentarios de cualquier video de YouTube. Sin apps, sin registro.';
$canonical  = 'https://mammoli.ar/sorteo/';

// Contador público de sorteos realizados
$total_sorteos_done = 0;
try {
    $total_sorteos_done = (int)get_db()->query("SELECT COUNT(*) FROM sorteos WHERE status = 'done'")->fetchColumn();
} catch (\Throwable $e) {}

// Si viene con ?v= lo incluimos en el canonical
$init_id = trim($_GET['v'] ?? '');
if ($init_id) {
    // Validación básica del formato UUID en PHP para no inyectar nada al HTML
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $init_id)) {
        $init_id = '';
    }
}

$js_init_id = $init_id ? json_encode($init_id) : 'null';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars($page_title) ?></title>
<meta name="description" content="<?= htmlspecialchars($og_desc) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">

<!-- Open Graph -->
<meta property="og:title"       content="Sorteador de YouTube">
<meta property="og:description" content="<?= htmlspecialchars($og_desc) ?>">
<meta property="og:url"         content="<?= htmlspecialchars($canonical) ?>">
<meta property="og:type"        content="website">
<meta name="twitter:card"       content="summary">

<style>
/* ── Reset & base ───────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #111827;
    --surface:   #1f2937;
    --border:    #374151;
    --text:      #f9fafb;
    --muted:     #9ca3af;
    --accent:    #3b82f6;
    --success:   #22c55e;
    --danger:    #ef4444;
    --warning:   #f59e0b;
    --radius:    12px;
    --radius-sm: 8px;
    --shadow:    0 1px 3px rgba(0,0,0,.3), 0 1px 2px rgba(0,0,0,.2);
    --shadow-md: 0 4px 12px rgba(0,0,0,.4);
}

html { height: 100%; }
body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100%;
    font-size: 15px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

/* ── Layout ─────────────────────────────────────────────────────────────────── */
.app {
    max-width: 520px;
    margin: 0 auto;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.header {
    background: #0f0f0f;
    border-bottom: 1px solid rgba(255,255,255,.1);
    padding: 10px 16px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.55);
}
.header-inner {
    display: flex;
    align-items: center;
    gap: 12px;
}
.header-brand {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.header-yt-link {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: opacity .15s;
}
.header-yt-link:hover { opacity: .8; }
.yt-icon { width: 38px; height: 27px; flex-shrink: 0; }
.header-title-group { min-width: 0; }
.header-title-line {
    display: flex;
    align-items: baseline;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.header-title-line h1 {
    font-size: 0; /* children set own sizes */
    display: contents;
}
.yt-wordmark {
    font-size: 17px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -.3px;
}
.header-picker-name {
    font-size: 17px;
    font-weight: 800;
    background: linear-gradient(135deg, #ef4444, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -.2px;
}
.header-tagline {
    font-size: 11px;
    color: rgba(255,255,255,.45);
    margin-top: 2px;
}

/* ── Main ────────────────────────────────────────────────────────────────────── */
.main { flex: 1; padding: 16px; display: flex; flex-direction: column; gap: 16px; }

/* ── Cards ───────────────────────────────────────────────────────────────────── */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    box-shadow: var(--shadow);
}

/* ── Inputs ──────────────────────────────────────────────────────────────────── */
.field { display: flex; flex-direction: column; gap: 6px; }
.field label {
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.field input[type=text],
.field input[type=url],
.field input[type=number],
.field input[type=date],
.field select {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    padding: 10px 12px;
    font-size: 15px;
    font-family: inherit;
    width: 100%;
    outline: none;
    transition: border-color .15s;
}
.field input:focus,
.field select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
.field select option { background: var(--surface); }

/* ── Checkbox row ────────────────────────────────────────────────────────────── */
.check-row {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 4px 0;
}
.check-row input[type=checkbox] {
    width: 18px;
    height: 18px;
    accent-color: var(--accent);
    cursor: pointer;
    flex-shrink: 0;
}
.check-row span {
    font-size: 14px;
    color: var(--text);
    user-select: none;
}

/* ── Buttons ─────────────────────────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 11px 20px;
    border-radius: var(--radius-sm);
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: opacity .15s, transform .1s;
}
.btn:active { transform: scale(.97); }
.btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.btn-primary { background: var(--accent); color: #fff; width: 100%; }
.btn-primary:hover:not(:disabled) { opacity: .9; }
.btn-success { background: var(--success); color: #fff; width: 100%; }
.btn-success:hover:not(:disabled) { opacity: .9; }
.btn-ghost {
    background: transparent;
    color: var(--muted);
    border: 1px solid var(--border);
    font-size: 13px;
    padding: 8px 14px;
}
.btn-ghost:hover { color: var(--text); border-color: var(--muted); }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { opacity: .9; }

/* ── Video preview ───────────────────────────────────────────────────────────── */
.video-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}
.video-thumb {
    width: 80px;
    height: 56px;
    object-fit: cover;
    border-radius: 6px;
    flex-shrink: 0;
    background: var(--border);
}
.video-thumb-placeholder {
    width: 80px;
    height: 56px;
    border-radius: 6px;
    background: var(--border);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.video-info { flex: 1; min-width: 0; }
.video-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.video-meta { font-size: 12px; color: var(--muted); margin-top: 3px; }

/* ── Progress bar ────────────────────────────────────────────────────────────── */
.progress-wrap { margin: 10px 0; }
.progress-label {
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 6px;
    display: flex;
    justify-content: space-between;
}
.progress-bar-bg {
    background: var(--border);
    border-radius: 99px;
    height: 8px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent), #818cf8);
    border-radius: 99px;
    transition: width .4s ease;
    width: 0%;
}
.progress-bar-fill.indeterminate {
    width: 40% !important;
    animation: indeterminate 1.4s ease-in-out infinite;
}
@keyframes indeterminate {
    0%   { transform: translateX(-100%); }
    100% { transform: translateX(350%); }
}
.progress-note { font-size: 12px; color: var(--warning); margin-top: 6px; }

/* ── Stats row ───────────────────────────────────────────────────────────────── */
.stats-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin: 10px 0;
}
.stat-chip {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 99px;
    padding: 4px 12px;
    font-size: 13px;
    color: var(--muted);
}
.stat-chip strong { color: var(--text); }

/* ── Winners list ────────────────────────────────────────────────────────────── */
.winners-header {
    text-align: center;
    margin-bottom: 16px;
}
.winners-header .trophy { font-size: 40px; display: block; margin-bottom: 6px; }
.winners-header h2 {
    font-size: 20px;
    font-weight: 800;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.winners-header .video-name {
    font-size: 12px;
    color: var(--muted);
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.winner-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.winner-item:last-child { border-bottom: none; }
.winner-pos {
    font-size: 22px;
    font-weight: 900;
    color: var(--warning);
    flex-shrink: 0;
    min-width: 32px;
    text-align: center;
    line-height: 1.2;
}
.winner-pos.pos-1 { color: #fbbf24; }
.winner-pos.pos-2 { color: #94a3b8; }
.winner-pos.pos-3 { color: #cd7f32; }
.winner-body { flex: 1; min-width: 0; }
.winner-author {
    font-weight: 700;
    font-size: 14px;
    color: var(--text);
}
.winner-comment {
    font-size: 13px;
    color: var(--muted);
    margin-top: 2px;
    word-break: break-word;
}
.winner-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 5px;
    flex-wrap: wrap;
}
.winner-link {
    font-size: 12px;
    color: var(--accent);
    text-decoration: none;
}
.winner-link:hover { text-decoration: underline; }
.winner-copy-btn {
    font-size: 12px;
    background: rgba(255,255,255,.06);
    border: 1px solid var(--border);
    border-radius: 5px;
    color: var(--muted);
    padding: 2px 8px;
    cursor: pointer;
    font-family: inherit;
    transition: all .15s;
}
.winner-copy-btn:hover { color: var(--text); border-color: #6b7280; }
.winner-copy-btn.copy-ok { color: var(--success); border-color: var(--success); }

/* ── Actions row ─────────────────────────────────────────────────────────────── */
.actions-row {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 4px;
}
.actions-row-h {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.actions-row-h .btn { flex: 1; min-width: 120px; }

/* ── Error box ───────────────────────────────────────────────────────────────── */
.error-box {
    background: rgba(239,68,68,.12);
    border: 1px solid rgba(239,68,68,.4);
    border-radius: var(--radius-sm);
    padding: 12px 14px;
    font-size: 14px;
    color: #fca5a5;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.error-icon { flex-shrink: 0; font-size: 16px; }

/* ── Spinner ─────────────────────────────────────────────────────────────────── */
.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .6s linear infinite;
    vertical-align: middle;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Section divider ─────────────────────────────────────────────────────────── */
.divider {
    height: 1px;
    background: var(--border);
    margin: 12px 0;
}

/* ── Options grid ────────────────────────────────────────────────────────────── */
.options-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.options-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media (max-width: 380px) {
    .options-row-2 { grid-template-columns: 1fr; }
}

/* ── Section label ───────────────────────────────────────────────────────────── */
.section-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 10px;
}

/* ── Toast ───────────────────────────────────────────────────────────────────── */
#toast {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 12px 18px;
    font-size: 14px;
    color: var(--text);
    box-shadow: var(--shadow-md);
    transition: transform .35s ease;
    z-index: 999;
    max-width: 340px;
    text-align: center;
    line-height: 1.4;
}
#toast.show { transform: translateX(-50%) translateY(0); }

/* ── Footer ──────────────────────────────────────────────────────────────────── */
footer {
    text-align: center;
    padding: 20px 16px 36px;
    color: var(--muted);
    font-size: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
footer a { color: var(--muted); text-decoration: none; }
footer a:hover { color: var(--text); }
.footer-cafecito {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #7c4a2d;
    color: #fff !important;
    border-radius: 20px;
    padding: 9px 20px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none !important;
    transition: opacity .15s, transform .1s;
    letter-spacing: .01em;
}
.footer-cafecito:hover { opacity: .85 !important; transform: scale(1.03); }
.footer-meta { font-size: 12px; color: var(--muted); }
.footer-meta a { color: var(--muted); text-decoration: none; }
.footer-meta a:hover { color: var(--text); }

/* ── State visibility ────────────────────────────────────────────────────────── */
.state { display: none; }
.state.active { display: flex; flex-direction: column; gap: 16px; }

/* ── Copy feedback ───────────────────────────────────────────────────────────── */
.copy-ok { color: var(--success) !important; }

/* ── Backup section ──────────────────────────────────────────────────────────── */
.backup-section {
    margin-top: 8px;
}
.winner-item.is-backup {
    opacity: .8;
}
.backup-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted);
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 1px 6px;
    margin-left: 6px;
    vertical-align: middle;
}

/* ── Responsive desktop ──────────────────────────────────────────────────────── */
@media (min-width: 640px) {
    .app { max-width: 700px; }
    .video-thumb         { width: 120px; height: 80px; }
    .video-thumb-placeholder { width: 120px; height: 80px; }
    .winners-list        { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .winner-item         { border-bottom: 1px solid var(--border); }
}

/* ── Festejo: dados overlay ──────────────────────────────────────────────────── */
#dice-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(17,24,39,.93);
    z-index: 500;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 24px;
}
#dice-overlay.visible { display: flex; }
#dice-row { display: flex; gap: 14px; }
.die {
    font-size: 60px;
    line-height: 1;
    display: inline-block;
    animation: die-shake .12s ease-in-out infinite alternate;
    will-change: transform;
    filter: drop-shadow(0 0 12px rgba(251,191,36,.5));
}
.die:nth-child(2) { animation-delay: .04s; font-size: 72px; }
.die:nth-child(3) { animation-delay: .08s; }
@keyframes die-shake {
    from { transform: rotate(-18deg) scale(1.06) translateY(-4px); }
    to   { transform: rotate( 18deg) scale(0.94) translateY( 4px); }
}
#dice-msg {
    color: var(--text);
    font-size: 20px;
    font-weight: 800;
    letter-spacing: .04em;
}

/* ── Festejo: check row highlight ────────────────────────────────────────────── */
.check-row-festejo span { color: #fbbf24; }

/* ── Animación de revelación de ganadores ────────────────────────────────────── */
@keyframes winner-pop {
    0%   { opacity: 0; transform: scale(.75) translateY(18px); }
    65%  { transform: scale(1.04) translateY(-3px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
.winner-item.pop-in {
    opacity: 0;
    animation: winner-pop .45s cubic-bezier(.34,1.56,.64,1) both;
}

/* ── Tema claro ──────────────────────────────────────────────────────────────── */
.theme-light {
    --bg:        #f1f5f9;
    --surface:   #ffffff;
    --border:    #cbd5e1;
    --text:      #0f172a;
    --muted:     #64748b;
    --accent:    #2563eb;
    --success:   #16a34a;
    --danger:    #dc2626;
    --warning:   #b45309;
    --shadow:    0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.05);
    --shadow-md: 0 4px 12px rgba(0,0,0,.1);
}
/* header stays dark in both themes — YouTube brand */
.theme-light .progress-bar-fill {
    background: linear-gradient(90deg, #2563eb, #6366f1);
}
.theme-light .field input[type=date] { color-scheme: light; }
.field input[type=date] { color-scheme: dark; }
.theme-light .stat-chip { background: #e2e8f0; }
.theme-light .backup-section { border-top-color: #cbd5e1; }
.theme-light .winner-copy-btn { background: #f1f5f9; }
.theme-light #dice-overlay { background: rgba(248,250,252,.97); }
.theme-light #dice-overlay #dice-msg { color: #1e293b; }
.theme-light .error-box { background: rgba(220,38,38,.08); border-color: rgba(220,38,38,.3); }
.theme-light .check-row-festejo span { color: #b45309; }

/* ── Selector idioma/tema ────────────────────────────────────────────────────── */
.header-controls {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-shrink: 0;
}
.ctrl-btn {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 6px;
    color: #f9fafb;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 8px;
    cursor: pointer;
    font-family: inherit;
    line-height: 1;
    transition: background .15s;
    letter-spacing: .03em;
}
.ctrl-btn:hover { background: rgba(255,255,255,.22); }
.header-sorteos-count {
    font-size: 11px;
    color: rgba(255,255,255,.45);
    margin-top: 2px;
    letter-spacing: .02em;
}
</style>
</head>
<body>
<div class="app">

    <!-- Header -->
    <header class="header">
        <div class="header-inner">
            <div class="header-brand">
                <a href="https://mammoli.ar" class="header-yt-link" aria-label="mammoli.ar">
                    <svg class="yt-icon" viewBox="0 0 90 63" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="0" y="0" width="90" height="63" rx="14" ry="14" fill="#FF0000"/>
                        <polygon points="35,18 65,31.5 35,45" fill="#ffffff"/>
                    </svg>
                </a>
                <div class="header-title-group">
                    <div class="header-title-line">
                        <h1><span class="yt-wordmark">YouTube</span><span class="header-picker-name" data-i18n="header_picker"> Sorteador</span></h1>
                    </div>
                    <div class="header-tagline" data-i18n="header_tagline">Sin apps · sin registro</div>
                    <?php if ($total_sorteos_done > 0): ?>
                    <div class="header-sorteos-count"><?= number_format($total_sorteos_done) ?> sorteos realizados</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-controls">
                <button class="ctrl-btn" id="btn-lang" title="Cambiar idioma / Change language">EN</button>
                <button class="ctrl-btn" id="btn-theme" title="Cambiar tema / Change theme">☀️</button>
            </div>
        </div>
    </header>

    <main class="main" id="main">

        <!-- ── Estado 1: Formulario ─────────────────────────────────────────── -->
        <div class="state active" id="state-form">
            <div class="card">
                <div id="video-urls-wrap">
                    <div class="video-url-row" data-idx="0">
                        <div class="field">
                            <label data-i18n="label_url">URL del video de YouTube</label>
                            <input type="url" class="yt-url-input" placeholder="https://www.youtube.com/watch?v=..." data-i18n-placeholder="placeholder_url" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-ghost" id="btn-add-video" style="margin-top:8px;font-size:13px" data-i18n="btn_add_video">+ Agregar otro video</button>
            </div>

            <div class="card">
                <div class="section-label" data-i18n="section_options">Opciones del sorteo</div>
                <div class="options-grid">
                    <div class="options-row-2">
                        <div class="field">
                            <label for="opt-winners" data-i18n="label_winners">Ganadores</label>
                            <input type="number" id="opt-winners" value="1" min="1" max="100">
                        </div>
                        <div class="field">
                            <label for="opt-max" data-i18n="label_max_comments">Límite comentarios</label>
                            <select id="opt-max">
                                <option value="1000">1.000</option>
                                <option value="5000">5.000</option>
                                <option value="10000" selected>10.000</option>
                                <option value="50000">50.000</option>
                                <option value="0" data-i18n="opt_unlimited">Sin límite</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label for="opt-keyword" data-i18n="label_keyword">Filtrar por palabra clave</label>
                        <input
                            type="text"
                            id="opt-keyword"
                            placeholder="Ej: #participo (opcional)"
                            data-i18n-placeholder="placeholder_keyword"
                            maxlength="100"
                        >
                    </div>

                    <div class="divider"></div>

                    <div class="field">
                        <label for="opt-max-per-user" data-i18n="label_max_per_user">Máximo por usuario</label>
                        <select id="opt-max-per-user">
                            <option value="1" selected data-i18n="opt_1_no_dup">1 (sin duplicados)</option>
                            <option value="2" data-i18n="opt_2_per">2 por usuario</option>
                            <option value="3" data-i18n="opt_3_per">3 por usuario</option>
                            <option value="5" data-i18n="opt_5_per">5 por usuario</option>
                            <option value="0" data-i18n="opt_unlimited">Sin límite</option>
                        </select>
                    </div>
                    <label class="check-row">
                        <input type="checkbox" id="opt-replies">
                        <span data-i18n="check_replies">Incluir respuestas</span>
                    </label>

                    <div class="divider"></div>
                    <div class="section-label" data-i18n="section_advanced">Filtros avanzados</div>

                    <div class="options-row-2">
                        <div class="field">
                            <label for="opt-date-from" data-i18n="label_date_from">Comentarios desde</label>
                            <input type="date" id="opt-date-from">
                        </div>
                        <div class="field">
                            <label for="opt-date-to" data-i18n="label_date_to">Comentarios hasta</label>
                            <input type="date" id="opt-date-to">
                        </div>
                    </div>

                    <div class="field">
                        <label for="opt-min-likes" data-i18n="label_min_likes">Mínimo de likes en el comentario</label>
                        <input type="number" id="opt-min-likes" value="0" min="0" placeholder="0 = sin filtro" data-i18n-placeholder="placeholder_min_likes">
                    </div>

                    <div class="field">
                        <label for="opt-exclude" data-i18n="label_exclude">Excluir usuarios (uno por línea, sin @)</label>
                        <textarea id="opt-exclude" rows="3" placeholder="canal_dueño&#10;moderador1&#10;mi_cuenta" data-i18n-placeholder="placeholder_exclude" style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);padding:10px 12px;font-size:14px;font-family:inherit;width:100%;outline:none;resize:vertical"></textarea>
                        <div id="exclude-hint" style="display:none;font-size:12px;color:var(--success);margin-top:4px"></div>
                    </div>

                    <div class="field">
                        <label for="opt-backups" data-i18n="label_backups">Ganadores suplentes</label>
                        <input type="number" id="opt-backups" value="0" min="0" max="20">
                    </div>

                    <div class="divider"></div>

                    <label class="check-row check-row-festejo">
                        <input type="checkbox" id="opt-festejo">
                        <span data-i18n="check_festejo">🎉 Modo festejo — dados al sortear y serpentinas con los resultados</span>
                    </label>
                </div>
            </div>

            <div id="form-error"></div>

            <button class="btn btn-primary" id="btn-buscar" data-i18n="btn_search">
                Buscar comentarios
            </button>
        </div>

        <!-- ── Estado 2: Descargando (SSE) ─────────────────────────────────── -->
        <div class="state" id="state-fetching">
            <div class="card">
                <div class="video-preview" id="fetch-preview">
                    <div class="video-thumb-placeholder" id="fetch-thumb-ph">▶</div>
                    <img class="video-thumb" id="fetch-thumb" src="" alt="" style="display:none">
                    <div class="video-info">
                        <div class="video-title" id="fetch-title" data-i18n="fetch_getting_info">Obteniendo información...</div>
                        <div class="video-meta" id="fetch-meta"></div>
                    </div>
                </div>

                <div class="progress-wrap">
                    <div class="progress-label">
                        <span id="fetch-count" data-i18n="fetch_connecting">Conectando...</span>
                        <span id="fetch-pct"></span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill indeterminate" id="fetch-bar"></div>
                    </div>
                    <div class="progress-note" id="fetch-note" style="display:none"></div>
                </div>
            </div>

            <button class="btn btn-ghost" onclick="cancelFetch()" data-i18n="btn_cancel">Cancelar y volver</button>
        </div>

        <!-- ── Estado 3: Listo para sortear ─────────────────────────────────── -->
        <div class="state" id="state-ready">
            <div class="card">
                <div class="video-preview">
                    <img class="video-thumb" id="ready-thumb" src="" alt="" style="display:none">
                    <div class="video-thumb-placeholder" id="ready-thumb-ph">▶</div>
                    <div class="video-info">
                        <div class="video-title" id="ready-title"></div>
                        <div class="video-meta" id="ready-meta"></div>
                    </div>
                </div>

                <div class="stats-row" id="ready-stats"></div>
            </div>

            <div id="ready-error"></div>

            <button class="btn btn-success" id="btn-sortear" data-i18n="btn_draw">
                🎰 ¡Sortear!
            </button>

            <a href="?" class="btn btn-ghost" style="text-align:center" data-i18n="btn_new_giveaway">← Nuevo sorteo</a>
        </div>

        <!-- ── Estado 4: Ganadores ───────────────────────────────────────────── -->
        <div class="state" id="state-winners">
            <div class="card">
                <div class="winners-header">
                    <span class="trophy">🎉</span>
                    <h2 data-i18n="title_winners_multiple">Ganadores del sorteo</h2>
                    <div class="video-name" id="w-video-name"></div>
                </div>
                <div id="winners-list"></div>
            </div>

            <div class="actions-row">
                <div class="actions-row-h">
                    <button class="btn btn-success" id="btn-resortear" data-i18n="btn_redraw">🔄 Sortear de nuevo</button>
                    <button class="btn btn-ghost" id="btn-share" data-i18n="btn_share">Compartir</button>
                </div>
                <a class="btn btn-ghost" id="btn-cert" href="#" target="_blank" data-i18n="btn_cert">📄 Certificado PDF</a>
                <a href="?" class="btn btn-ghost" style="text-align:center" data-i18n="btn_new_giveaway">← Nuevo sorteo</a>
            </div>
        </div>

        <!-- ── Estado: Error global ──────────────────────────────────────────── -->
        <div class="state" id="state-error">
            <div class="error-box">
                <span class="error-icon">⚠</span>
                <span id="error-msg">Ocurrió un error.</span>
            </div>
            <a href="?" class="btn btn-ghost" style="text-align:center" data-i18n="btn_back">← Volver</a>
        </div>

    </main>

    <footer>
        <a href="https://cafecito.app/mammoli" class="footer-cafecito" target="_blank" rel="noopener" data-i18n="footer_cafecito">☕ Invitame un cafecito</a>
        <div class="footer-meta">
            <a href="https://mammoli.ar">mammoli.ar</a>
            · <span data-i18n="footer_brand">Sorteador de YouTube</span> v2.0
            · <a href="verificar.php" data-i18n="footer_verify">Verificar certificado</a>
            · <a href="stats.php" data-i18n="footer_stats">Estadísticas</a>
        </div>
    </footer>
</div>

<!-- Dados overlay (modo festejo) -->
<div id="dice-overlay">
    <div id="dice-row">
        <span class="die">⚄</span>
        <span class="die">⚂</span>
        <span class="die">⚅</span>
    </div>
    <div id="dice-msg"><span id="dice-msg-text" data-i18n="dice_msg">Sorteando</span><span id="dice-dots">...</span></div>
</div>

<!-- Toast cafecito -->
<div id="toast">
    <span data-i18n="toast_msg">☕ ¿Te resultó útil?</span>
    <a href="https://cafecito.app/mammoli" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none;font-weight:600;margin-left:4px;" data-i18n="toast_link">Invitame un cafecito</a>
</div>

<script>
(function() {
'use strict';

// ── Traducciones ──────────────────────────────────────────────────────────────
var LANGS = {
  es: {
    label_url:           'URL del video de YouTube',
    placeholder_url:     'https://www.youtube.com/watch?v=...',
    btn_add_video:       '+ Agregar otro video',
    section_options:     'Opciones del sorteo',
    label_winners:       'Ganadores',
    label_max_comments:  'Límite comentarios',
    opt_unlimited:       'Sin límite',
    label_keyword:       'Filtrar por palabra clave',
    placeholder_keyword: 'Ej: #participo (opcional)',
    label_max_per_user:  'Máximo por usuario',
    opt_1_no_dup:        '1 (sin duplicados)',
    opt_2_per:           '2 por usuario',
    opt_3_per:           '3 por usuario',
    opt_5_per:           '5 por usuario',
    check_replies:       'Incluir respuestas',
    section_advanced:    'Filtros avanzados',
    label_date_from:     'Comentarios desde',
    label_date_to:       'Comentarios hasta',
    label_min_likes:     'Mínimo de likes en el comentario',
    placeholder_min_likes:'0 = sin filtro',
    label_exclude:       'Excluir usuarios (uno por línea, sin @)',
    placeholder_exclude: 'canal_dueño\nmoderador1\nmi_cuenta',
    label_backups:       'Ganadores suplentes',
    check_festejo:       '🎉 Modo festejo — dados al sortear y serpentinas con los resultados',
    btn_search:          'Buscar comentarios',
    fetch_getting_info:  'Obteniendo información...',
    fetch_connecting:    'Conectando...',
    btn_cancel:          'Cancelar y volver',
    btn_draw:            '🎰 ¡Sortear!',
    btn_new_giveaway:    '← Nuevo sorteo',
    btn_back:            '← Volver',
    title_winner_single:   'Ganador del sorteo',
    title_winners_multiple:'Ganadores del sorteo',
    btn_redraw:          '🔄 Sortear de nuevo',
    btn_share:           'Compartir',
    btn_share_copied:    '✓ ¡Copiado!',
    btn_cert:            '📄 Certificado PDF',
    dice_msg:            'Sorteando',
    footer_brand:        'Sorteador de YouTube',
    toast_msg:           '☕ ¿Te resultó útil?',
    toast_link:          'Invitame un cafecito',
    // dinámicas
    chip_downloaded:     '{0} descargados',
    chip_filter:         'Filtro: {0}',
    chip_1_per_user:     '1 por usuario',
    chip_n_per_user:     '{0} por usuario',
    chip_winners_count:  '{0} ganador(es)',
    fetch_video_n_of_m:  'Video {0} de {1} — conectando...',
    fetch_downloading:   'Descargando... {0} / {1}',
    fetch_downloading_unk:'Descargando... {0} comentarios',
    fetch_comments_total:'{0} comentarios en el video',
    fetch_limit_note:    'Límite: {0} de ~{1} disponibles',
    backups_section:     'Suplentes',
    badge_backup:        'Suplente',
    winner_view_comment: 'Ver comentario en YouTube ↗',
    winner_copy_mention: '📋 Copiar mención',
    winner_copied:       '✓ Copiado',
    mention_single:      '¡Felicitaciones! Sos el ganador del sorteo 🏆',
    mention_multiple:    '¡Felicitaciones! Sos uno de los ganadores del sorteo 🎉',
    hint_channel_owner:  '✓ Dueño del canal agregado automáticamente',
    btn_drawing:         'Sorteando...',
    btn_creating:        'Creando sorteo...',
    btn_redrawing:       'Sorteando...',
    // errores
    err_no_url:          'Ingresá al menos una URL de YouTube.',
    err_winners_range:   'La cantidad de ganadores debe ser entre 1 y 100.',
    err_create_fail:     'No se pudo crear el sorteo. Revisá tu conexión.',
    err_conn_lost:       'Se perdió la conexión. Recargá la página para reintentar.',
    err_load_fail:       'Error al cargar el sorteo.',
    err_sort_fail:       'No se pudo sortear. Revisá tu conexión.',
    err_download_fail:   'Error al descargar comentarios.',
    video_fallback_title:'Video de YouTube',
    prompt_copy_mention: 'Copiá esta mención:',
    prompt_copy_link:    'Copiá este enlace:',
    header_picker:       ' Sorteador',
    header_tagline:      'Sin apps · sin registro',
    footer_cafecito:     '☕ Invitame un cafecito',
    footer_verify:       'Verificar certificado',
    footer_stats:        'Estadísticas',
  },
  en: {
    label_url:           'YouTube Video URL',
    placeholder_url:     'https://www.youtube.com/watch?v=...',
    btn_add_video:       '+ Add another video',
    section_options:     'Giveaway Options',
    label_winners:       'Winners',
    label_max_comments:  'Comment Limit',
    opt_unlimited:       'No limit',
    label_keyword:       'Filter by keyword',
    placeholder_keyword: 'E.g.: #enter (optional)',
    label_max_per_user:  'Max per user',
    opt_1_no_dup:        '1 (no duplicates)',
    opt_2_per:           '2 per user',
    opt_3_per:           '3 per user',
    opt_5_per:           '5 per user',
    check_replies:       'Include replies',
    section_advanced:    'Advanced Filters',
    label_date_from:     'Comments from',
    label_date_to:       'Comments until',
    label_min_likes:     'Minimum likes on comment',
    placeholder_min_likes:'0 = no filter',
    label_exclude:       'Exclude users (one per line, no @)',
    placeholder_exclude: 'channel_owner\nmoderator1\nmy_account',
    label_backups:       'Backup Winners',
    check_festejo:       '🎉 Party mode — dice during draw and confetti with results',
    btn_search:          'Search Comments',
    fetch_getting_info:  'Getting information...',
    fetch_connecting:    'Connecting...',
    btn_cancel:          'Cancel and go back',
    btn_draw:            '🎰 Draw!',
    btn_new_giveaway:    '← New Giveaway',
    btn_back:            '← Back',
    title_winner_single:   'Giveaway Winner',
    title_winners_multiple:'Giveaway Winners',
    btn_redraw:          '🔄 Draw Again',
    btn_share:           'Share',
    btn_share_copied:    '✓ Copied!',
    btn_cert:            '📄 PDF Certificate',
    dice_msg:            'Drawing',
    footer_brand:        'YouTube Comment Picker',
    toast_msg:           '☕ Found it useful?',
    toast_link:          'Buy me a coffee',
    // dynamic
    chip_downloaded:     '{0} downloaded',
    chip_filter:         'Filter: {0}',
    chip_1_per_user:     '1 per user',
    chip_n_per_user:     '{0} per user',
    chip_winners_count:  '{0} winner(s)',
    fetch_video_n_of_m:  'Video {0} of {1} — connecting...',
    fetch_downloading:   'Downloading... {0} / {1}',
    fetch_downloading_unk:'Downloading... {0} comments',
    fetch_comments_total:'{0} comments on the video',
    fetch_limit_note:    'Limit: {0} of ~{1} available',
    backups_section:     'Backup Winners',
    badge_backup:        'Backup',
    winner_view_comment: 'View comment on YouTube ↗',
    winner_copy_mention: '📋 Copy mention',
    winner_copied:       '✓ Copied',
    mention_single:      'Congratulations! You\'re the giveaway winner 🏆',
    mention_multiple:    'Congratulations! You\'re one of the giveaway winners 🎉',
    hint_channel_owner:  '✓ Channel owner added automatically',
    btn_drawing:         'Drawing...',
    btn_creating:        'Creating giveaway...',
    btn_redrawing:       'Drawing...',
    // errors
    err_no_url:          'Enter at least one YouTube URL.',
    err_winners_range:   'Number of winners must be between 1 and 100.',
    err_create_fail:     'Could not create the giveaway. Check your connection.',
    err_conn_lost:       'Connection lost. Reload the page to retry.',
    err_load_fail:       'Error loading giveaway.',
    err_sort_fail:       'Could not draw. Check your connection.',
    err_download_fail:   'Error downloading comments.',
    video_fallback_title:'YouTube Video',
    prompt_copy_mention: 'Copy this mention:',
    prompt_copy_link:    'Copy this link:',
    header_picker:       ' Comment Picker',
    header_tagline:      'No apps · no sign-up',
    footer_cafecito:     '☕ Buy me a coffee',
    footer_verify:       'Verify certificate',
    footer_stats:        'Stats',
  }
};

var currentLang  = localStorage.getItem('sorteo_lang')  || 'es';
var currentTheme = localStorage.getItem('sorteo_theme') || 'dark';

function t(key) {
    return (LANGS[currentLang] || LANGS.es)[key] || key;
}
function tf(key /*, args... */) {
    var s    = t(key);
    var args = Array.prototype.slice.call(arguments, 1);
    return s.replace(/\{(\d+)\}/g, function(_, i) {
        return args[+i] !== undefined ? String(args[+i]) : '';
    });
}

function applyTheme(theme) {
    currentTheme = theme;
    localStorage.setItem('sorteo_theme', theme);
    document.documentElement.classList.toggle('theme-light', theme === 'light');
    document.getElementById('btn-theme').textContent = theme === 'light' ? '🌙' : '☀️';
}

function applyLang(lang) {
    currentLang = lang;
    localStorage.setItem('sorteo_lang', lang);
    document.documentElement.lang = lang;
    document.getElementById('btn-lang').textContent = lang === 'es' ? 'EN' : 'ES';

    // Elementos con data-i18n (textContent)
    document.querySelectorAll('[data-i18n]').forEach(function(el) {
        el.textContent = t(el.dataset.i18n);
    });
    // Elementos con data-i18n-placeholder
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function(el) {
        el.placeholder = t(el.dataset.i18nPlaceholder);
    });

    // Re-renderizar estado actual si hay datos
    var activeState = document.querySelector('.state.active');
    var sid = activeState ? activeState.id : '';
    if (sid === 'state-winners' && currentData) {
        renderWinnersList(
            currentData.winners || [],
            currentData.backups || [],
            currentData.video_id || '',
            currentData.video_title || ''
        );
    }
    if (sid === 'state-ready' && currentData) {
        renderReady(currentData, currentData.total_fetched || 0);
    }
}

// ── Estado de la app ──────────────────────────────────────────────────────────
let currentId   = null;
let currentData = null;
let eventSource = null;
let festejoMode = false;

// ── Dados ─────────────────────────────────────────────────────────────────────
var diceFaces    = ['⚀','⚁','⚂','⚃','⚄','⚅'];
var diceTimer    = null;
var dotsTimer    = null;

function showDice() {
    var overlay = document.getElementById('dice-overlay');
    var dice    = overlay.querySelectorAll('.die');
    overlay.classList.add('visible');
    diceTimer = setInterval(function() {
        dice.forEach(function(d) {
            d.textContent = diceFaces[Math.floor(Math.random() * 6)];
        });
    }, 90);
    var dots = document.getElementById('dice-dots');
    dots.textContent = '.';
    dotsTimer = setInterval(function() {
        dots.textContent = dots.textContent.length >= 3 ? '.' : dots.textContent + '.';
    }, 350);
}

function hideDice() {
    clearInterval(diceTimer);
    clearInterval(dotsTimer);
    document.getElementById('dice-overlay').classList.remove('visible');
}

// ── Confetti ──────────────────────────────────────────────────────────────────
function launchConfetti() {
    var canvas  = document.createElement('canvas');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:999';
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
    document.body.appendChild(canvas);
    var ctx     = canvas.getContext('2d');
    var colors  = ['#ef4444','#f59e0b','#22c55e','#3b82f6','#a855f7','#ec4899','#f97316','#fbbf24'];
    var parts   = [];

    for (var i = 0; i < 220; i++) {
        parts.push({
            x:    Math.random() * canvas.width,
            y:   -20 - Math.random() * canvas.height * 0.6,
            w:    7  + Math.random() * 9,
            h:    3  + Math.random() * 5,
            c:    colors[i % colors.length],
            vx:  (Math.random() - 0.5) * 4,
            vy:   1.5 + Math.random() * 3.5,
            rot:  Math.random() * Math.PI * 2,
            rv:  (Math.random() - 0.5) * 0.18,
            a:    1,
        });
    }

    var start = null;
    function frame(ts) {
        if (!start) start = ts;
        var el = ts - start;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        var alive = 0;
        parts.forEach(function(p) {
            p.x += p.vx; p.y += p.vy; p.vy += 0.07; p.rot += p.rv;
            if (el > 3200) p.a -= 0.014;
            if (p.a <= 0 || p.y > canvas.height + 20) return;
            alive++;
            ctx.save();
            ctx.globalAlpha = Math.max(0, p.a);
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);
            ctx.fillStyle = p.c;
            ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
            ctx.restore();
        });
        if (alive > 0) requestAnimationFrame(frame);
        else canvas.remove();
    }
    requestAnimationFrame(frame);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function showState(name) {
    document.querySelectorAll('.state').forEach(function(el) {
        el.classList.remove('active');
    });
    var el = document.getElementById('state-' + name);
    if (el) el.classList.add('active');
}

function showError(msg) {
    document.getElementById('error-msg').textContent = msg;
    showState('error');
}

function showInlineError(containerId, msg) {
    var el = document.getElementById(containerId);
    if (!el) return;
    if (!msg) { el.innerHTML = ''; return; }
    el.innerHTML = '<div class="error-box"><span class="error-icon">⚠</span><span>' +
        escHtml(msg) + '</span></div>';
}

function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function fmtNum(n) {
    return Number(n).toLocaleString(currentLang === 'en' ? 'en-US' : 'es-AR');
}

// ── Init ──────────────────────────────────────────────────────────────────────
var initId = <?= $js_init_id ?>;

// Aplicar tema e idioma persistidos
applyTheme(currentTheme);
applyLang(currentLang);

// Wiring controles de idioma y tema
document.getElementById('btn-lang').addEventListener('click', function() {
    applyLang(currentLang === 'es' ? 'en' : 'es');
});
document.getElementById('btn-theme').addEventListener('click', function() {
    applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
});

if (initId) {
    currentId = initId;
    // Consultar estado en API
    fetch('api.php?action=get&id=' + encodeURIComponent(initId))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                // ID no encontrado → mostrar formulario limpio
                history.replaceState(null, '', '?');
                showState('form');
                return;
            }
            currentData = data;
            if (data.status === 'done') {
                renderWinners(data);
            } else if (data.status === 'ready') {
                renderReady(data, data.total_fetched);
            } else {
                // fetching o pending → reconectar SSE
                showFetchingUI();
                openSSE(initId);
            }
        })
        .catch(function() {
            history.replaceState(null, '', '?');
            showState('form');
        });
} else {
    showState('form');
}

// ── Formulario ────────────────────────────────────────────────────────────────
document.getElementById('btn-buscar').addEventListener('click', startSorteo);

// Agregar/quitar videos
document.getElementById('btn-add-video').addEventListener('click', function() {
    var wrap = document.getElementById('video-urls-wrap');
    var rows = wrap.querySelectorAll('.video-url-row');
    if (rows.length >= 5) return;
    var idx = rows.length;
    var div = document.createElement('div');
    div.className = 'video-url-row';
    div.dataset.idx = idx;
    div.innerHTML = '<div class="field" style="flex:1"><label>Video ' + (idx + 1) + '</label>' +
        '<input type="url" class="yt-url-input" placeholder="https://www.youtube.com/watch?v=..." autocomplete="off"></div>' +
        '<button type="button" class="btn-remove-video" title="Quitar" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;padding:0 4px;align-self:flex-end;margin-bottom:2px">\xd7</button>';
    div.style.display = 'flex'; div.style.gap = '8px'; div.style.alignItems = 'flex-start';
    div.querySelector('.btn-remove-video').addEventListener('click', function() {
        div.remove();
        if (wrap.querySelectorAll('.video-url-row').length < 5) {
            document.getElementById('btn-add-video').style.display = '';
        }
    });
    wrap.appendChild(div);
    if (rows.length + 1 >= 5) document.getElementById('btn-add-video').style.display = 'none';
});

function startSorteo() {
    showInlineError('form-error', '');
    var videoUrls = Array.from(document.querySelectorAll('.yt-url-input'))
        .map(function(i) { return i.value.trim(); })
        .filter(Boolean);
    if (!videoUrls.length) {
        showInlineError('form-error', t('err_no_url'));
        return;
    }
    var winners    = parseInt(document.getElementById('opt-winners').value, 10);
    var maxC       = parseInt(document.getElementById('opt-max').value, 10);
    var keyword    = document.getElementById('opt-keyword').value.trim();
    var maxPerUser = parseInt(document.getElementById('opt-max-per-user').value, 10);
    var replies    = document.getElementById('opt-replies').checked;
    var dateFrom   = document.getElementById('opt-date-from').value;
    var dateTo     = document.getElementById('opt-date-to').value;
    var minLikes   = parseInt(document.getElementById('opt-min-likes').value, 10) || 0;
    var excludeRaw = document.getElementById('opt-exclude').value;
    var excludeUsers = excludeRaw.split('\n').map(function(s){return s.trim().replace(/^@/,'');}).filter(Boolean);
    var numBackups = parseInt(document.getElementById('opt-backups').value, 10) || 0;

    if (isNaN(winners) || winners < 1 || winners > 100) {
        showInlineError('form-error', t('err_winners_range'));
        return;
    }

    festejoMode = document.getElementById('opt-festejo').checked;

    var btn = document.getElementById('btn-buscar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> ' + t('btn_creating');

    fetch('api.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            video_urls:      videoUrls,
            num_winners:     winners,
            max_comments:    maxC,
            keyword:         keyword,
            max_per_user:    maxPerUser,
            include_replies: replies,
            date_from:       dateFrom,
            date_to:         dateTo,
            min_likes:       minLikes,
            exclude_users:   excludeUsers,
            num_backups:     numBackups
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = t('btn_search');
        if (data.error) {
            showInlineError('form-error', data.error);
            return;
        }
        // Auto-excluir dueño del canal si el campo está vacío
        if (data.channel_owner) {
            var excl = document.getElementById('opt-exclude');
            if (!excl.value.trim()) {
                excl.value = data.channel_owner;
                var hint = document.getElementById('exclude-hint');
                if (hint) {
                    hint.textContent = t('hint_channel_owner');
                    hint.style.display = 'block';
                    setTimeout(function() { hint.style.display = 'none'; }, 4000);
                }
            }
        }
        currentId = data.id;
        history.replaceState(null, '', '?v=' + encodeURIComponent(data.id));
        showFetchingUI();
        openSSE(data.id);
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = t('btn_search');
        showInlineError('form-error', t('err_create_fail'));
    });
}

// ── SSE ───────────────────────────────────────────────────────────────────────
function showFetchingUI() {
    showState('fetching');
    // Resetear
    document.getElementById('fetch-title').textContent = t('fetch_getting_info');
    document.getElementById('fetch-meta').textContent = '';
    document.getElementById('fetch-count').textContent = t('fetch_connecting');
    document.getElementById('fetch-pct').textContent = '';
    document.getElementById('fetch-note').style.display = 'none';
    var bar = document.getElementById('fetch-bar');
    bar.style.width = '0%';
    bar.classList.add('indeterminate');
    var thumb = document.getElementById('fetch-thumb');
    thumb.style.display = 'none';
    document.getElementById('fetch-thumb-ph').style.display = 'flex';
}

function openSSE(id) {
    if (eventSource) { eventSource.close(); eventSource = null; }

    eventSource = new EventSource('fetch.php?id=' + encodeURIComponent(id));

    var commentCount = 0;
    var maxComments  = 0;

    eventSource.onmessage = function(e) {
        var msg;
        try { msg = JSON.parse(e.data); } catch(ex) { return; }

        if (msg.type === 'video_start') {
            document.getElementById('fetch-count').textContent =
                msg.total > 1
                    ? tf('fetch_video_n_of_m', msg.index + 1, msg.total)
                    : t('fetch_connecting');
            return;
        }

        if (msg.type === 'already_done') {
            eventSource.close();
            // Recargar estado desde API
            fetch('api.php?action=get&id=' + encodeURIComponent(id))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) { showError(data.error); return; }
                    currentData = data;
                    if (data.status === 'done') {
                        renderWinners(data);
                    } else {
                        renderReady(data, data.total_fetched);
                    }
                })
                .catch(function() { showError(t('err_load_fail')); });
            return;
        }

        if (msg.type === 'info') {
            document.getElementById('fetch-title').textContent = msg.title;
            commentCount = msg.comment_count;
            maxComments  = msg.max;
            document.getElementById('fetch-meta').textContent =
                tf('fetch_comments_total', fmtNum(msg.comment_count));

            if (msg.thumb) {
                var img = document.getElementById('fetch-thumb');
                img.src = msg.thumb;
                img.style.display = 'block';
                document.getElementById('fetch-thumb-ph').style.display = 'none';
            }

            if (maxComments < commentCount) {
                var note = document.getElementById('fetch-note');
                note.style.display = 'block';
                note.textContent = tf('fetch_limit_note', fmtNum(maxComments), fmtNum(commentCount));
            }

            document.getElementById('fetch-bar').classList.remove('indeterminate');
            updateProgress(0, maxComments);
            return;
        }

        if (msg.type === 'progress') {
            updateProgress(msg.loaded, maxComments);
            return;
        }

        if (msg.type === 'done') {
            eventSource.close();
            eventSource = null;
            updateProgress(msg.total, maxComments);
            // Cargar data completa
            fetch('api.php?action=get&id=' + encodeURIComponent(id))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) { showError(data.error); return; }
                    currentData = data;
                    renderReady(data, msg.total);
                })
                .catch(function() {
                    renderReady({ video_id: id, options: {} }, msg.total);
                });
            return;
        }

        if (msg.type === 'error') {
            eventSource.close();
            eventSource = null;
            showError(msg.msg || t('err_download_fail'));
            return;
        }
    };

    eventSource.onerror = function() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        showError(t('err_conn_lost'));
    };
}

function updateProgress(loaded, max) {
    var bar   = document.getElementById('fetch-bar');
    var count = document.getElementById('fetch-count');
    var pct   = document.getElementById('fetch-pct');

    if (max > 0 && max < 999999) {
        var p = Math.min(100, Math.round(loaded / max * 100));
        bar.style.width = p + '%';
        pct.textContent = p + '%';
        count.textContent = tf('fetch_downloading', fmtNum(loaded), fmtNum(max));
    } else {
        bar.style.width = '60%';
        pct.textContent = '';
        count.textContent = tf('fetch_downloading_unk', fmtNum(loaded));
    }
}

function cancelFetch() {
    if (eventSource) { eventSource.close(); eventSource = null; }
    history.replaceState(null, '', '?');
    currentId   = null;
    currentData = null;
    showState('form');
}

// ── Estado 3: Listo ───────────────────────────────────────────────────────────
function renderReady(data, total) {
    var opts = data.options || {};

    // Thumb
    var thumb = document.getElementById('ready-thumb');
    var ph    = document.getElementById('ready-thumb-ph');
    if (data.video_thumb) {
        thumb.src = data.video_thumb;
        thumb.style.display = 'block';
        ph.style.display = 'none';
    } else {
        thumb.style.display = 'none';
        ph.style.display = 'flex';
    }

    document.getElementById('ready-title').textContent =
        data.video_title || t('video_fallback_title');

    var metaParts = [];
    if (data.video_comment_count) {
        metaParts.push(tf('fetch_comments_total', fmtNum(data.video_comment_count)));
    }
    document.getElementById('ready-meta').textContent = metaParts.join(' · ');

    // Stats chips
    var maxPU = opts.max_per_user !== undefined ? opts.max_per_user : (opts.unique_users ? 1 : 0);
    var chips = '';
    chips += '<div class="stat-chip">' + tf('chip_downloaded', '<strong>' + fmtNum(total) + '</strong>') + '</div>';
    if (opts.keyword) {
        chips += '<div class="stat-chip">' + tf('chip_filter', '<strong>' + escHtml(opts.keyword) + '</strong>') + '</div>';
    }
    if (maxPU > 0) {
        chips += '<div class="stat-chip">' + (maxPU === 1 ? t('chip_1_per_user') : tf('chip_n_per_user', maxPU)) + '</div>';
    }
    chips += '<div class="stat-chip">' + tf('chip_winners_count', '<strong>' + (opts.num_winners || 1) + '</strong>') + '</div>';
    document.getElementById('ready-stats').innerHTML = chips;

    showInlineError('ready-error', '');
    showState('ready');
}

// ── Sortear ───────────────────────────────────────────────────────────────────
document.getElementById('btn-sortear').addEventListener('click', doSortear);

function doSortear() {
    if (!currentId) return;
    var btn = document.getElementById('btn-sortear');
    btn.disabled = true;
    showInlineError('ready-error', '');

    if (festejoMode) {
        showDice();
    } else {
        btn.innerHTML = '<span class="spinner"></span> ' + t('btn_drawing');
    }

    fetch('api.php?action=sortear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (festejoMode) {
            setTimeout(function() {
                hideDice();
                btn.disabled = false;
                btn.innerHTML = t('btn_draw');
                if (data.error) { showInlineError('ready-error', data.error); return; }
                renderWinnersFromResult(data);
                launchConfetti();
            }, 900);
        } else {
            btn.disabled = false;
            btn.innerHTML = t('btn_draw');
            if (data.error) { showInlineError('ready-error', data.error); return; }
            renderWinnersFromResult(data);
        }
    })
    .catch(function() {
        hideDice();
        btn.disabled = false;
        btn.innerHTML = t('btn_draw');
        showInlineError('ready-error', t('err_sort_fail'));
    });
}

// ── Re-sortear ────────────────────────────────────────────────────────────────
document.getElementById('btn-resortear').addEventListener('click', function() {
    if (!currentId) return;
    var btn = this;
    btn.disabled = true;

    if (festejoMode) {
        showDice();
    } else {
        btn.innerHTML = '<span class="spinner"></span> ' + t('btn_redrawing');
    }

    fetch('api.php?action=sortear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (festejoMode) {
            setTimeout(function() {
                hideDice();
                btn.disabled = false;
                btn.innerHTML = t('btn_redraw');
                if (data.error) { showError(data.error); return; }
                renderWinnersFromResult(data);
                launchConfetti();
            }, 900);
        } else {
            btn.disabled = false;
            btn.innerHTML = t('btn_redraw');
            if (data.error) { showError(data.error); return; }
            renderWinnersFromResult(data);
        }
    })
    .catch(function() {
        hideDice();
        btn.disabled = false;
        btn.innerHTML = t('btn_redraw');
        showError(t('err_sort_fail'));
    });
});

// ── Renderizar ganadores ──────────────────────────────────────────────────────
function renderWinnersFromResult(data) {
    var videoId    = currentData ? currentData.video_id : '';
    var videoTitle = currentData ? (currentData.video_title || '') : '';
    renderWinnersList(data.winners, data.backups || [], videoId, videoTitle);
}

function renderWinners(data) {
    currentData = data;
    renderWinnersList(data.winners || [], data.backups || [], data.video_id, data.video_title || '');
}

function renderWinnersList(winners, backups, videoId, videoTitle) {
    document.getElementById('w-video-name').textContent = videoTitle;

    var medals = ['🥇', '🥈', '🥉'];
    var posClasses = ['pos-1', 'pos-2', 'pos-3'];
    var single = winners.length === 1;

    document.querySelector('#state-winners .winners-header h2').textContent =
        single ? t('title_winner_single') : t('title_winners_multiple');
    document.querySelector('#state-winners .trophy').textContent =
        single ? '🏆' : '🎉';

    // Detectar si hay más de un video distinto entre los ganadores
    var distinctVids = {};
    winners.forEach(function(w) { if (w.source_video_id) distinctVids[w.source_video_id] = 1; });
    var multiVid = Object.keys(distinctVids).length > 1;

    var html = '';
    winners.forEach(function(w, i) {
        var pos      = w.position || (i + 1);
        var posLabel = medals[pos - 1] || '#' + pos;
        var posClass = posClasses[pos - 1] || '';

        var commentText = w.text || '';
        if (commentText.length > 120) commentText = commentText.substring(0, 120) + '...';

        // Para el lc= usar solo el ID base (antes del punto) para que YouTube
        // navegue al hilo incluso cuando el ganador es una respuesta
        var baseCommentId = w.comment_id ? w.comment_id.split('.')[0] : '';
        var vidForLink = (multiVid && w.source_video_id) ? w.source_video_id : videoId;
        var ytLink = 'https://www.youtube.com/watch?v=' +
            encodeURIComponent(vidForLink) + '&lc=' + encodeURIComponent(baseCommentId);

        var mention = '@' + w.author + ' ' + (single ? t('mention_single') : t('mention_multiple'));

        html += '<div class="winner-item">';
        html += '<div class="winner-pos ' + posClass + '">' + posLabel + '</div>';
        html += '<div class="winner-body">';
        html += '<div class="winner-author">@' + escHtml(w.author) + '</div>';
        if (multiVid && w.source_video_id) {
            html += '<div style="font-size:11px;color:var(--muted);margin-top:1px">youtu.be/' + escHtml(w.source_video_id) + '</div>';
        }
        if (commentText) {
            html += '<div class="winner-comment">' + escHtml(commentText) + '</div>';
        }
        html += '<div class="winner-actions">';
        html += '<a class="winner-link" href="' + escHtml(ytLink) +
            '" target="_blank" rel="noopener">' + t('winner_view_comment') + '</a>';
        html += '<button class="winner-copy-btn" data-mention="' + escHtml(mention) +
            '" title="' + t('winner_copy_mention') + '">' + t('winner_copy_mention') + '</button>';
        html += '</div>';
        html += '</div></div>';
    });

    if (backups && backups.length > 0) {
        html += '<div class="backup-section">';
        html += '<div style="font-size:12px;color:var(--muted);text-align:center;padding:10px 0;border-top:1px dashed var(--border);margin-top:8px">' + t('backups_section') + '</div>';
        backups.forEach(function(w, i) {
            var pos         = w.position || (i + 1);
            var commentText = w.text || '';
            if (commentText.length > 120) commentText = commentText.substring(0, 120) + '...';
            var baseCommentId = w.comment_id ? w.comment_id.split('.')[0] : '';
            var ytLink = 'https://www.youtube.com/watch?v=' +
                encodeURIComponent(videoId) + '&lc=' + encodeURIComponent(baseCommentId);
            var mention = '@' + w.author + ' ' + t('mention_multiple');

            html += '<div class="winner-item is-backup">';
            html += '<div class="winner-pos">#' + pos + '</div>';
            html += '<div class="winner-body">';
            html += '<div class="winner-author">@' + escHtml(w.author) +
                '<span class="backup-badge">' + t('badge_backup') + '</span></div>';
            if (commentText) {
                html += '<div class="winner-comment">' + escHtml(commentText) + '</div>';
            }
            html += '<div class="winner-actions">';
            html += '<a class="winner-link" href="' + escHtml(ytLink) +
                '" target="_blank" rel="noopener">' + t('winner_view_comment') + '</a>';
            html += '<button class="winner-copy-btn" data-mention="' + escHtml(mention) +
                '" title="' + t('winner_copy_mention') + '">' + t('winner_copy_mention') + '</button>';
            html += '</div>';
            html += '</div></div>';
        });
        html += '</div>';
    }

    document.getElementById('winners-list').innerHTML = html;

    // Botones de copiar mención
    document.querySelectorAll('.winner-copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var text = btn.dataset.mention;
            navigator.clipboard.writeText(text).then(function() {
                var orig = btn.innerHTML;
                btn.innerHTML = t('winner_copied');
                btn.classList.add('copy-ok');
                setTimeout(function() { btn.innerHTML = orig; btn.classList.remove('copy-ok'); }, 2000);
            }).catch(function() { prompt(t('prompt_copy_mention'), text); });
        });
    });

    showState('winners');

    // Animación de revelación de ganadores (solo en modo festejo)
    if (festejoMode) {
        var items = document.querySelectorAll('#winners-list .winner-item:not(.is-backup)');
        items.forEach(function(el, i) {
            el.classList.add('pop-in');
            el.style.animationDelay = (i * 380) + 'ms';
        });
    }

    document.getElementById('btn-cert').href = 'certificate.php?v=' + encodeURIComponent(currentId) + '&lang=' + currentLang;
}

// ── Compartir ─────────────────────────────────────────────────────────────────
document.getElementById('btn-share').addEventListener('click', function() {
    if (!currentId) return;
    var url = location.origin + location.pathname + '?v=' + encodeURIComponent(currentId);
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            var btn = document.getElementById('btn-share');
            var orig = btn.innerHTML;
            btn.innerHTML = t('btn_share_copied');
            btn.classList.add('copy-ok');
            setTimeout(function() {
                btn.innerHTML = orig;
                btn.classList.remove('copy-ok');
            }, 2000);
        }).catch(function() {
            prompt(t('prompt_copy_link'), url);
        });
    } else {
        prompt(t('prompt_copy_link'), url);
    }
});

// ── Toast cafecito (25s delay, sessionStorage guard) ──────────────────────────
if (!sessionStorage.getItem('toast_shown')) {
    setTimeout(function() {
        var t = document.getElementById('toast');
        t.classList.add('show');
        sessionStorage.setItem('toast_shown', '1');
        t.addEventListener('click', function() {
            t.classList.remove('show');
        });
        setTimeout(function() {
            t.classList.remove('show');
        }, 8000);
    }, 25000);
}

})();
</script>
<script>
// Detectar zona horaria del navegador y guardarla en cookie para PHP
try {
    var _tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (_tz) {
        document.cookie = 'sorteo_tz=' + encodeURIComponent(_tz) + '; path=/sorteo/; max-age=86400; SameSite=Lax';
    }
} catch(e) {}
</script>
</body>
</html>
