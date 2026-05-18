<?php
// index.php — Sorteador de YouTube

$page_title = 'Sorteador de YouTube — Sorteos de comentarios sin apps';
$og_desc    = 'Sorteá ganadores entre los comentarios de cualquier video de YouTube. Sin apps, sin registro.';
$canonical  = 'https://mammoli.ar/sorteo/';

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
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 14px 16px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: var(--shadow);
}
.header-inner {
    display: flex;
    align-items: center;
    gap: 10px;
}
.header-logo {
    font-size: 24px;
    line-height: 1;
    flex-shrink: 0;
    text-decoration: none;
}
.header-title { flex: 1; min-width: 0; }
.header-title h1 {
    font-size: 18px;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: linear-gradient(135deg, #ef4444, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.header-title .subtitle {
    font-size: 12px;
    color: var(--muted);
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
    padding: 24px 16px 32px;
    color: var(--muted);
    font-size: 12px;
}
footer a { color: var(--muted); text-decoration: none; }
footer a:hover { color: var(--text); }

/* ── State visibility ────────────────────────────────────────────────────────── */
.state { display: none; }
.state.active { display: flex; flex-direction: column; gap: 16px; }

/* ── Copy feedback ───────────────────────────────────────────────────────────── */
.copy-ok { color: var(--success) !important; }

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
</style>
</head>
<body>
<div class="app">

    <!-- Header -->
    <header class="header">
        <div class="header-inner">
            <span class="header-logo">🎰</span>
            <div class="header-title">
                <h1>Sorteador de YouTube</h1>
                <div class="subtitle">Sorteos de comentarios · sin apps · sin registro</div>
            </div>
        </div>
    </header>

    <main class="main" id="main">

        <!-- ── Estado 1: Formulario ─────────────────────────────────────────── -->
        <div class="state active" id="state-form">
            <div class="card">
                <div class="field">
                    <label for="yt-url">URL del video de YouTube</label>
                    <input
                        type="url"
                        id="yt-url"
                        placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </div>
            </div>

            <div class="card">
                <div class="section-label">Opciones del sorteo</div>
                <div class="options-grid">
                    <div class="options-row-2">
                        <div class="field">
                            <label for="opt-winners">Ganadores</label>
                            <input type="number" id="opt-winners" value="1" min="1" max="100">
                        </div>
                        <div class="field">
                            <label for="opt-max">Límite comentarios</label>
                            <select id="opt-max">
                                <option value="1000">1.000</option>
                                <option value="5000">5.000</option>
                                <option value="10000" selected>10.000</option>
                                <option value="50000">50.000</option>
                                <option value="0">Sin límite</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label for="opt-keyword">Filtrar por palabra clave</label>
                        <input
                            type="text"
                            id="opt-keyword"
                            placeholder="Ej: #participo (opcional)"
                            maxlength="100"
                        >
                    </div>

                    <div class="divider"></div>

                    <label class="check-row">
                        <input type="checkbox" id="opt-unique" checked>
                        <span>Un sorteo por usuario (ignora comentarios múltiples del mismo)</span>
                    </label>
                    <label class="check-row">
                        <input type="checkbox" id="opt-replies">
                        <span>Incluir respuestas</span>
                    </label>

                    <div class="divider"></div>

                    <label class="check-row check-row-festejo">
                        <input type="checkbox" id="opt-festejo">
                        <span>🎉 Modo festejo — dados al sortear y serpentinas con los resultados</span>
                    </label>
                </div>
            </div>

            <div id="form-error"></div>

            <button class="btn btn-primary" id="btn-buscar">
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
                        <div class="video-title" id="fetch-title">Obteniendo información...</div>
                        <div class="video-meta" id="fetch-meta"></div>
                    </div>
                </div>

                <div class="progress-wrap">
                    <div class="progress-label">
                        <span id="fetch-count">Conectando...</span>
                        <span id="fetch-pct"></span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill indeterminate" id="fetch-bar"></div>
                    </div>
                    <div class="progress-note" id="fetch-note" style="display:none"></div>
                </div>
            </div>

            <button class="btn btn-ghost" onclick="cancelFetch()">Cancelar y volver</button>
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

            <button class="btn btn-success" id="btn-sortear">
                🎰 ¡Sortear!
            </button>

            <a href="?" class="btn btn-ghost" style="text-align:center">← Nuevo sorteo</a>
        </div>

        <!-- ── Estado 4: Ganadores ───────────────────────────────────────────── -->
        <div class="state" id="state-winners">
            <div class="card">
                <div class="winners-header">
                    <span class="trophy">🎉</span>
                    <h2>Ganadores del sorteo</h2>
                    <div class="video-name" id="w-video-name"></div>
                </div>
                <div id="winners-list"></div>
            </div>

            <div class="actions-row">
                <div class="actions-row-h">
                    <button class="btn btn-success" id="btn-resortear">🔄 Sortear de nuevo</button>
                    <button class="btn btn-ghost" id="btn-share">Compartir</button>
                </div>
                <a href="?" class="btn btn-ghost" style="text-align:center">← Nuevo sorteo</a>
            </div>
        </div>

        <!-- ── Estado: Error global ──────────────────────────────────────────── -->
        <div class="state" id="state-error">
            <div class="error-box">
                <span class="error-icon">⚠</span>
                <span id="error-msg">Ocurrió un error.</span>
            </div>
            <a href="?" class="btn btn-ghost" style="text-align:center">← Volver</a>
        </div>

    </main>

    <footer>
        <a href="https://mammoli.ar">mammoli.ar</a> · Sorteador de YouTube v1.0
    </footer>
</div>

<!-- Dados overlay (modo festejo) -->
<div id="dice-overlay">
    <div id="dice-row">
        <span class="die">⚄</span>
        <span class="die">⚂</span>
        <span class="die">⚅</span>
    </div>
    <div id="dice-msg">Sorteando<span id="dice-dots">...</span></div>
</div>

<!-- Toast cafecito -->
<div id="toast">
    ☕ ¿Te resultó útil?
    <a href="https://cafecito.app/mammoli" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none;font-weight:600;margin-left:4px;">
        Invitame un cafecito
    </a>
</div>

<script>
(function() {
'use strict';

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
    return Number(n).toLocaleString('es-AR');
}

// ── Init ──────────────────────────────────────────────────────────────────────
var initId = <?= $js_init_id ?>;

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

document.getElementById('yt-url').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') startSorteo();
});

function startSorteo() {
    showInlineError('form-error', '');
    var url      = document.getElementById('yt-url').value.trim();
    var winners  = parseInt(document.getElementById('opt-winners').value, 10);
    var maxC     = parseInt(document.getElementById('opt-max').value, 10);
    var keyword  = document.getElementById('opt-keyword').value.trim();
    var unique   = document.getElementById('opt-unique').checked;
    var replies  = document.getElementById('opt-replies').checked;

    if (!url) {
        showInlineError('form-error', 'Ingresá la URL del video de YouTube.');
        document.getElementById('yt-url').focus();
        return;
    }
    if (isNaN(winners) || winners < 1 || winners > 100) {
        showInlineError('form-error', 'La cantidad de ganadores debe ser entre 1 y 100.');
        return;
    }

    festejoMode = document.getElementById('opt-festejo').checked;

    var btn = document.getElementById('btn-buscar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Creando sorteo...';

    fetch('api.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            url:             url,
            num_winners:     winners,
            max_comments:    maxC,
            keyword:         keyword,
            unique_users:    unique,
            include_replies: replies
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = 'Buscar comentarios';
        if (data.error) {
            showInlineError('form-error', data.error);
            return;
        }
        currentId = data.id;
        history.replaceState(null, '', '?v=' + encodeURIComponent(data.id));
        showFetchingUI();
        openSSE(data.id);
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = 'Buscar comentarios';
        showInlineError('form-error', 'No se pudo crear el sorteo. Revisá tu conexión.');
    });
}

// ── SSE ───────────────────────────────────────────────────────────────────────
function showFetchingUI() {
    showState('fetching');
    // Resetear
    document.getElementById('fetch-title').textContent = 'Obteniendo información...';
    document.getElementById('fetch-meta').textContent = '';
    document.getElementById('fetch-count').textContent = 'Conectando...';
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
                .catch(function() { showError('Error al cargar el sorteo.'); });
            return;
        }

        if (msg.type === 'info') {
            document.getElementById('fetch-title').textContent = msg.title;
            commentCount = msg.comment_count;
            maxComments  = msg.max;
            document.getElementById('fetch-meta').textContent =
                fmtNum(msg.comment_count) + ' comentarios en el video';

            if (msg.thumb) {
                var img = document.getElementById('fetch-thumb');
                img.src = msg.thumb;
                img.style.display = 'block';
                document.getElementById('fetch-thumb-ph').style.display = 'none';
            }

            if (maxComments < commentCount) {
                var note = document.getElementById('fetch-note');
                note.style.display = 'block';
                note.textContent = 'Límite: ' + fmtNum(maxComments) +
                    ' de ~' + fmtNum(commentCount) + ' disponibles';
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
                    // Fallback sin thumb
                    renderReady({ video_id: id, options: {} }, msg.total);
                });
            return;
        }

        if (msg.type === 'error') {
            eventSource.close();
            eventSource = null;
            showError(msg.msg || 'Error al descargar comentarios.');
            return;
        }
    };

    eventSource.onerror = function() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        showError('Se perdió la conexión con el servidor. Recargá la página para reintentar.');
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
        count.textContent = 'Descargando... ' + fmtNum(loaded) + ' / ' + fmtNum(max);
    } else {
        bar.style.width = '60%';
        pct.textContent = '';
        count.textContent = 'Descargando... ' + fmtNum(loaded) + ' comentarios';
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
        data.video_title || 'Video de YouTube';

    var metaParts = [];
    if (data.video_comment_count) {
        metaParts.push(fmtNum(data.video_comment_count) + ' comentarios totales');
    }
    document.getElementById('ready-meta').textContent = metaParts.join(' · ');

    // Stats chips
    var chips = '';
    chips += '<div class="stat-chip"><strong>' + fmtNum(total) + '</strong> descargados</div>';
    if (opts.keyword) {
        chips += '<div class="stat-chip">Filtro: <strong>' + escHtml(opts.keyword) + '</strong></div>';
    }
    if (opts.unique_users) {
        chips += '<div class="stat-chip">1 por usuario</div>';
    }
    chips += '<div class="stat-chip"><strong>' + (opts.num_winners || 1) + '</strong> ganador(es)</div>';
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
        btn.innerHTML = '<span class="spinner"></span> Sorteando...';
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
                btn.innerHTML = '🎰 ¡Sortear!';
                if (data.error) { showInlineError('ready-error', data.error); return; }
                renderWinnersFromResult(data);
                launchConfetti();
            }, 900);
        } else {
            btn.disabled = false;
            btn.innerHTML = '🎰 ¡Sortear!';
            if (data.error) { showInlineError('ready-error', data.error); return; }
            renderWinnersFromResult(data);
        }
    })
    .catch(function() {
        hideDice();
        btn.disabled = false;
        btn.innerHTML = '🎰 ¡Sortear!';
        showInlineError('ready-error', 'No se pudo sortear. Revisá tu conexión.');
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
        btn.innerHTML = '<span class="spinner"></span> Sorteando...';
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
                btn.innerHTML = '🔄 Sortear de nuevo';
                if (data.error) { showError(data.error); return; }
                renderWinnersFromResult(data);
                launchConfetti();
            }, 900);
        } else {
            btn.disabled = false;
            btn.innerHTML = '🔄 Sortear de nuevo';
            if (data.error) { showError(data.error); return; }
            renderWinnersFromResult(data);
        }
    })
    .catch(function() {
        hideDice();
        btn.disabled = false;
        btn.innerHTML = '🔄 Sortear de nuevo';
        showError('No se pudo sortear. Revisá tu conexión.');
    });
});

// ── Renderizar ganadores ──────────────────────────────────────────────────────
function renderWinnersFromResult(data) {
    var videoId    = currentData ? currentData.video_id : '';
    var videoTitle = currentData ? (currentData.video_title || '') : '';
    renderWinnersList(data.winners, videoId, videoTitle);
}

function renderWinners(data) {
    currentData = data;
    renderWinnersList(data.winners || [], data.video_id, data.video_title || '');
}

function renderWinnersList(winners, videoId, videoTitle) {
    document.getElementById('w-video-name').textContent = videoTitle;

    var medals = ['🥇', '🥈', '🥉'];
    var posClasses = ['pos-1', 'pos-2', 'pos-3'];
    var single = winners.length === 1;

    document.querySelector('#state-winners .winners-header h2').textContent =
        single ? 'Ganador del sorteo' : 'Ganadores del sorteo';
    document.querySelector('#state-winners .trophy').textContent =
        single ? '🏆' : '🎉';

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
        var ytLink = 'https://www.youtube.com/watch?v=' +
            encodeURIComponent(videoId) + '&lc=' + encodeURIComponent(baseCommentId);

        var mention = single
            ? '@' + w.author + ' ¡Felicitaciones! Sos el ganador del sorteo 🏆'
            : '@' + w.author + ' ¡Felicitaciones! Sos uno de los ganadores del sorteo 🎉';

        html += '<div class="winner-item">';
        html += '<div class="winner-pos ' + posClass + '">' + posLabel + '</div>';
        html += '<div class="winner-body">';
        html += '<div class="winner-author">@' + escHtml(w.author) + '</div>';
        if (commentText) {
            html += '<div class="winner-comment">' + escHtml(commentText) + '</div>';
        }
        html += '<div class="winner-actions">';
        html += '<a class="winner-link" href="' + escHtml(ytLink) +
            '" target="_blank" rel="noopener">Ver comentario ↗</a>';
        html += '<button class="winner-copy-btn" data-mention="' + escHtml(mention) +
            '" title="Copiar mención para notificar al ganador">📋 Copiar mención</button>';
        html += '</div>';
        html += '</div></div>';
    });

    document.getElementById('winners-list').innerHTML = html;

    // Botones de copiar mención
    document.querySelectorAll('.winner-copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var text = btn.dataset.mention;
            navigator.clipboard.writeText(text).then(function() {
                var orig = btn.innerHTML;
                btn.innerHTML = '✓ Copiado';
                btn.classList.add('copy-ok');
                setTimeout(function() { btn.innerHTML = orig; btn.classList.remove('copy-ok'); }, 2000);
            }).catch(function() { prompt('Copiá esta mención:', text); });
        });
    });

    showState('winners');
}

// ── Compartir ─────────────────────────────────────────────────────────────────
document.getElementById('btn-share').addEventListener('click', function() {
    if (!currentId) return;
    var url = location.origin + location.pathname + '?v=' + encodeURIComponent(currentId);
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() {
            var btn = document.getElementById('btn-share');
            var orig = btn.innerHTML;
            btn.innerHTML = '✓ ¡Copiado!';
            btn.classList.add('copy-ok');
            setTimeout(function() {
                btn.innerHTML = orig;
                btn.classList.remove('copy-ok');
            }, 2000);
        }).catch(function() {
            prompt('Copiá este enlace:', url);
        });
    } else {
        prompt('Copiá este enlace:', url);
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
</body>
</html>
