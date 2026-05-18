<?php
// stats.php — Estadísticas públicas del Sorteador de YouTube
require_once __DIR__ . '/db.php';

$pdo = get_db();

// Totales
$total_sorteos = (int)$pdo->query("SELECT COUNT(*) FROM sorteos WHERE status = 'done'")->fetchColumn();
$total_canales = (int)$pdo->query(
    "SELECT COUNT(DISTINCT channel_title) FROM sorteos WHERE status = 'done' AND channel_title IS NOT NULL AND channel_title != ''"
)->fetchColumn();

// Por canal: conteo y última fecha
$por_canal = $pdo->query(
    "SELECT channel_title,
            COUNT(*) AS total,
            MAX(created_at) AS ultimo
     FROM sorteos
     WHERE status = 'done'
       AND channel_title IS NOT NULL AND channel_title != ''
     GROUP BY channel_title
     ORDER BY total DESC, ultimo DESC
     LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);

// Últimos 20 sorteos realizados
$recientes = $pdo->query(
    "SELECT video_title, channel_title, created_at
     FROM sorteos
     WHERE status = 'done'
     ORDER BY created_at DESC
     LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

function fmt_fecha(string $dt): string {
    $ts = strtotime($dt);
    if (!$ts) return $dt;
    $d = (int)((time() - $ts) / 86400);
    if ($d === 0) return 'hoy';
    if ($d === 1) return 'ayer';
    if ($d < 7) return "hace $d días";
    return date('d/m/Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Estadísticas · Sorteador de YouTube</title>
<meta name="robots" content="noindex">
<style>
:root {
  --bg: #f1f5f9;
  --surface: #fff;
  --border: #e2e8f0;
  --text: #0f172a;
  --muted: #64748b;
  --accent: #ef4444;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
header {
  background: #0f0f0f; color: #fff; padding: 14px 20px;
  display: flex; align-items: center; gap: 12px;
}
header svg { width: 36px; height: 25px; flex-shrink: 0; }
header h1 { font-size: 1rem; font-weight: 700; }
header .sub { font-size: .8rem; color: #aaa; }
main { max-width: 700px; margin: 32px auto; padding: 0 16px 40px; }
.stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
.stat-card { background: var(--surface); border-radius: 12px; padding: 20px; text-align: center; border: 1px solid var(--border); }
.stat-card .num { font-size: 2.2rem; font-weight: 800; color: var(--accent); }
.stat-card .lbl { font-size: .8rem; color: var(--muted); margin-top: 4px; }
h2 { font-size: 1rem; font-weight: 700; margin-bottom: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
.section { margin-bottom: 32px; }
.canal-row { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.canal-name { font-weight: 600; font-size: .92rem; }
.canal-meta { display: flex; gap: 10px; align-items: center; flex-shrink: 0; }
.badge { background: #fef2f2; color: var(--accent); font-size: .75rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
.fecha { font-size: .75rem; color: var(--muted); }
.sorteo-row { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; margin-bottom: 6px; }
.sorteo-titulo { font-size: .88rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sorteo-canal { font-size: .78rem; color: var(--muted); }
footer { text-align: center; color: var(--muted); font-size: .78rem; padding: 12px; }
footer a { color: var(--muted); }
@media (max-width: 400px) { .stat-grid { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>

<header>
  <svg viewBox="0 0 90 63">
    <rect x="0" y="0" width="90" height="63" rx="14" ry="14" fill="#FF0000"/>
    <polygon points="35,18 65,31.5 35,45" fill="#ffffff"/>
  </svg>
  <div>
    <h1>Sorteador de YouTube · Stats</h1>
    <div class="sub">Estadísticas de uso — mammoli.ar/sorteo</div>
  </div>
</header>

<main>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="num"><?= number_format($total_sorteos) ?></div>
      <div class="lbl">sorteos realizados</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= number_format($total_canales) ?></div>
      <div class="lbl">canales distintos</div>
    </div>
  </div>

  <?php if ($por_canal): ?>
  <div class="section">
    <h2>Por canal</h2>
    <?php foreach ($por_canal as $c): ?>
    <div class="canal-row">
      <div class="canal-name"><?= htmlspecialchars($c['channel_title']) ?></div>
      <div class="canal-meta">
        <span class="badge"><?= $c['total'] ?> sorteo<?= $c['total'] != 1 ? 's' : '' ?></span>
        <span class="fecha"><?= fmt_fecha($c['ultimo']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($recientes): ?>
  <div class="section">
    <h2>Últimos sorteos</h2>
    <?php foreach ($recientes as $s): ?>
    <div class="sorteo-row">
      <div class="sorteo-titulo"><?= htmlspecialchars($s['video_title'] ?: '(sin título)') ?></div>
      <div class="sorteo-canal">
        <?= $s['channel_title'] ? htmlspecialchars($s['channel_title']) . ' · ' : '' ?><?= fmt_fecha($s['created_at']) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!$total_sorteos): ?>
  <div style="text-align:center;color:var(--muted);padding:40px 0">
    <div style="font-size:2rem;margin-bottom:8px">🎰</div>
    <div>Aún no hay sorteos registrados.</div>
    <a href="/sorteo/" style="display:inline-block;margin-top:12px;color:var(--accent)">¡Hacer el primero →</a>
  </div>
  <?php endif; ?>

</main>

<footer>
  <a href="/sorteo/">← Volver al sorteador</a>
  · <a href="https://mammoli.ar">mammoli.ar</a>
  · Desarrollado por <a href="https://mammoli.ar">Carlos Mammoli</a>
</footer>

</body>
</html>
