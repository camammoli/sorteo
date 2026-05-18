<?php
// admin.php — Panel secreto del Sorteador de YouTube
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// Autenticación por query param
if (!defined('SORTEO_ADMIN_KEY') || ($_GET['key'] ?? '') !== SORTEO_ADMIN_KEY) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$pdo = get_db();

// Paginación
$per_page = 50;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $per_page;

$total = (int)$pdo->query("SELECT COUNT(*) FROM sorteos")->fetchColumn();
$total_done = (int)$pdo->query("SELECT COUNT(*) FROM sorteos WHERE status='done'")->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$sorteos = $pdo->prepare("SELECT * FROM sorteos ORDER BY created_at DESC LIMIT ? OFFSET ?");
$sorteos->execute([$per_page, $offset]);
$sorteos = $sorteos->fetchAll(PDO::FETCH_ASSOC);

// Stats rate limit (último 24h)
$rate_24h = $pdo->query(
    "SELECT ip_hash, SUM(count) AS total FROM sorteo_rate
     WHERE window_start >= " . (time() - 86400) . "
     GROUP BY ip_hash ORDER BY total DESC LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

function fmt_options(array $opts): string {
    $parts = [];
    if (!empty($opts['num_winners']))     $parts[] = $opts['num_winners'] . ' ganador(es)';
    if (!empty($opts['num_backups']))     $parts[] = $opts['num_backups'] . ' suplentes';
    if (!empty($opts['keyword']))         $parts[] = 'kw: ' . htmlspecialchars($opts['keyword']);
    if (!empty($opts['date_from']))       $parts[] = 'desde ' . $opts['date_from'];
    if (!empty($opts['date_to']))         $parts[] = 'hasta ' . $opts['date_to'];
    if (!empty($opts['max_per_user']))    $parts[] = 'max ' . $opts['max_per_user'] . '/usuario';
    if (!empty($opts['include_replies'])) $parts[] = 'respuestas: sí';
    if (!empty($opts['min_likes']))       $parts[] = '≥' . $opts['min_likes'] . ' likes';
    if (!empty($opts['max_comments']) && $opts['max_comments'] > 0) $parts[] = 'lím ' . number_format($opts['max_comments']);
    if (!empty($opts['exclude_users']))   $parts[] = 'excl: ' . implode(', ', array_map('htmlspecialchars', (array)$opts['exclude_users']));
    return implode(' · ', $parts);
}

function status_badge(string $s): string {
    $colors = [
        'done'     => '#22c55e',
        'ready'    => '#3b82f6',
        'fetching' => '#f59e0b',
        'pending'  => '#9ca3af',
    ];
    $c = $colors[$s] ?? '#9ca3af';
    return "<span style='background:{$c};color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px'>{$s}</span>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin · Sorteador de YouTube</title>
<meta name="robots" content="noindex, nofollow">
<style>
:root {
  --bg: #0f172a;
  --surface: #1e293b;
  --border: #334155;
  --text: #f1f5f9;
  --muted: #94a3b8;
  --accent: #ef4444;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, monospace; background: var(--bg); color: var(--text); font-size: 13px; min-height: 100vh; }
header { background: #1a1a2e; border-bottom: 2px solid var(--accent); padding: 12px 20px; display: flex; align-items: center; gap: 12px; }
header h1 { font-size: 1rem; font-weight: 700; color: var(--accent); }
header .sub { font-size: .75rem; color: var(--muted); margin-top: 2px; }
main { max-width: 1400px; margin: 24px auto; padding: 0 16px 60px; }
.stat-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.stat-box { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 14px 20px; min-width: 140px; }
.stat-box .num { font-size: 1.8rem; font-weight: 800; color: var(--accent); }
.stat-box .lbl { font-size: .75rem; color: var(--muted); margin-top: 2px; }
h2 { font-size: .85rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; margin-top: 24px; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th { text-align: left; padding: 8px 10px; border-bottom: 2px solid var(--border); color: var(--muted); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
td { padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
tr:hover td { background: rgba(255,255,255,.03); }
.cell-id { font-family: monospace; color: var(--muted); font-size: 11px; }
.cell-channel { font-weight: 600; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cell-title { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cell-ip { font-family: monospace; font-size: 11px; color: #60a5fa; }
.cell-opts { max-width: 200px; font-size: 11px; color: var(--muted); }
.cell-date { white-space: nowrap; color: var(--muted); font-size: 11px; }
.cell-fetch { text-align: right; color: var(--muted); }
.pagination { display: flex; gap: 6px; margin-top: 20px; align-items: center; flex-wrap: wrap; }
.pagination a, .pagination span { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 4px 10px; color: var(--text); text-decoration: none; font-size: 12px; }
.pagination .current { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 700; }
.pagination a:hover { background: #334155; }
.rate-table td, .rate-table th { font-size: 11px; }
footer { text-align: center; color: var(--muted); font-size: .75rem; padding: 12px; margin-top: 24px; }
footer a { color: var(--muted); }
</style>
</head>
<body>

<header>
  <div>
    <h1>🔒 Admin · Sorteador de YouTube</h1>
    <div class="sub">Panel de administración — mammoli.ar/sorteo/ — <?= date('d/m/Y H:i') ?></div>
  </div>
</header>

<main>

  <div class="stat-row">
    <div class="stat-box">
      <div class="num"><?= number_format($total) ?></div>
      <div class="lbl">total sorteos</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= number_format($total_done) ?></div>
      <div class="lbl">completados</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= number_format($total - $total_done) ?></div>
      <div class="lbl">incompletos</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= count($rate_24h) ?></div>
      <div class="lbl">IPs activas (24h)</div>
    </div>
  </div>

  <?php if ($rate_24h): ?>
  <h2>Rate limit — últimas 24h (top IPs)</h2>
  <table class="rate-table" style="max-width:400px">
    <tr><th>IP (hash parcial)</th><th>Sorteos</th></tr>
    <?php foreach ($rate_24h as $r): ?>
    <tr>
      <td class="cell-ip">...<?= substr($r['ip_hash'], -16) ?></td>
      <td><?= (int)$r['total'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <h2>Sorteos (<?= $total ?> total · página <?= $page ?> de <?= $total_pages ?>)</h2>
  <div style="overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Canal</th>
        <th>Video</th>
        <th>IP (hash parcial)</th>
        <th>Opciones</th>
        <th>Estado</th>
        <th>Comentarios</th>
        <th>Creado</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($sorteos as $s): ?>
      <?php
        $opts = $s['options'] ? json_decode($s['options'], true) : [];
        $vid  = $s['video_id'] ?? '';
        $vids_extra = '';
        if ($s['video_ids']) {
            $all_vids = json_decode($s['video_ids'], true) ?: [];
            if (count($all_vids) > 1) $vids_extra = ' <span style="color:var(--muted)">+' . (count($all_vids)-1) . '</span>';
        }
      ?>
      <tr>
        <td class="cell-id" title="<?= htmlspecialchars($s['id']) ?>"><?= substr($s['id'], 0, 8) ?>…</td>
        <td class="cell-channel" title="<?= htmlspecialchars($s['channel_title'] ?? '') ?>"><?= htmlspecialchars($s['channel_title'] ?? '—') ?></td>
        <td class="cell-title">
          <?php if ($vid): ?>
          <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($vid) ?>" target="_blank" rel="noopener" style="color:#3b82f6;text-decoration:none" title="<?= htmlspecialchars($s['video_title'] ?? '') ?>">
            <?= htmlspecialchars(mb_substr($s['video_title'] ?? $vid, 0, 60)) ?>
          </a><?= $vids_extra ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="cell-ip"><?= $s['ip_hash'] ? '…' . substr($s['ip_hash'], -12) : '—' ?></td>
        <td class="cell-opts"><?= $opts ? fmt_options($opts) : '—' ?></td>
        <td><?= status_badge($s['status'] ?? 'pending') ?></td>
        <td class="cell-fetch"><?= number_format((int)$s['total_fetched']) ?></td>
        <td class="cell-date"><?= htmlspecialchars($s['created_at'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?key=<?= urlencode(SORTEO_ADMIN_KEY) ?>&p=<?= $page-1 ?>">← Ant</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <?php if ($i === $page): ?>
      <span class="current"><?= $i ?></span>
      <?php elseif ($i <= 2 || $i >= $total_pages-1 || abs($i - $page) <= 2): ?>
      <a href="?key=<?= urlencode(SORTEO_ADMIN_KEY) ?>&p=<?= $i ?>"><?= $i ?></a>
      <?php elseif (abs($i - $page) === 3): ?>
      <span>…</span>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
    <a href="?key=<?= urlencode(SORTEO_ADMIN_KEY) ?>&p=<?= $page+1 ?>">Sig →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</main>

<footer>
  <a href="/sorteo/">← Volver al sorteador</a>
  · <a href="/sorteo/stats.php">Estadísticas públicas</a>
</footer>

</body>
</html>
