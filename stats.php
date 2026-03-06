<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php"); exit();
}
$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$perm = (int)($user['permissions'] ?? 0);
$current = 'stats';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Player-Stats | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        .stat-count { color:#888; font-size:12px; margin-bottom:12px; }
        .sort-bar { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:14px; }
        .sort-bar label { font-size:12px; color:#888; margin:0; }
    </style>
</head>
<body class="panel-layout">
<?php include 'nav_helper.php'; ?>
<div class="panel-wrapper">
    <div class="panel-content">
        <div class="page-header">
            <h2>Player-Stats</h2>
        </div>

        <?php if ($perm < 2): ?>
        <div class="alert alert-warning">
            <i class="fa fa-lock"></i> Berechtigung 2 benötigt, um Statistiken zu sehen.
        </div>
        <?php else: ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    Statistiken für <strong><?= htmlspecialchars($user['username']) ?></strong>
                    <span id="stat-count" class="stat-count" style="margin-left:10px;"></span>
                </h3>
            </div>
            <div class="panel-body">
                <div class="sort-bar">
                    <input class="form-control" id="stat-search" placeholder="Filtern…" style="max-width:280px;">
                    <label>Sortierung:</label>
                    <select id="sort-sel" class="form-control" style="width:auto;">
                        <option value="alpha">A–Z</option>
                        <option value="value-desc">Wert ↓</option>
                        <option value="value-asc">Wert ↑</option>
                    </select>
                </div>
                <div id="stats-grid" class="metrics-grid">
                    <div class="metric"><div class="metric-name">Lade…</div><div class="metric-value">–</div></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">Minecraft Dashboard &copy; <?php echo date('Y'); ?></div>
            <div class="col-md-6 text-right"><a href="#">Datenschutz</a> | <a href="#">Impressum</a></div>
        </div>
    </div>
</div>
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<?php if ($perm >= 2): ?>
<script>
const grid     = document.getElementById('stats-grid');
const search   = document.getElementById('stat-search');
const sortSel  = document.getElementById('sort-sel');
const countEl  = document.getElementById('stat-count');
let allStats   = [];

// Farbgebung: Tode/Damage → rot, gute Stats → grün, sonst blau
function statColor(key) {
    const k = key.toLowerCase();
    if (k.includes('death') || k.includes('damage') || k.includes('kill')) return 'red';
    if (k.includes('play') || k.includes('walk') || k.includes('sprint') || k.includes('distance') || k.includes('time')) return 'green';
    return '';
}

function renderStats() {
    const q = search.value.toLowerCase();
    let filtered = allStats.filter(m => !q || m.title.toLowerCase().includes(q) || m.key.toLowerCase().includes(q));

    const sort = sortSel.value;
    if (sort === 'value-desc') filtered.sort((a,b) => b.raw - a.raw);
    else if (sort === 'value-asc') filtered.sort((a,b) => a.raw - b.raw);
    else filtered.sort((a,b) => a.title.localeCompare(b.title));

    countEl.textContent = '(' + filtered.length + ' von ' + allStats.length + ')';

    if (filtered.length === 0) {
        grid.innerHTML = '<div class="alert alert-info">Keine Statistiken gefunden. Server offline oder keine Daten für ' +
            '<?= htmlspecialchars($user["username"]) ?>.</div>';
        return;
    }

    grid.innerHTML = '';
    filtered.forEach(m => {
        const el = document.createElement('div');
        el.className = 'metric ' + statColor(m.key);
        el.innerHTML = `<div class="metric-name" title="${m.key}">${m.title}</div><div class="metric-value">${m.value}</div>`;
        el.title = m.key;
        grid.appendChild(el);
    });
}

async function loadStats() {
    try {
        const r = await fetch('player_metrics.php');
        const d = await r.json();
        if (!d.ok) {
            grid.innerHTML = '<div class="alert alert-danger">' + (d.error || 'Fehler beim Laden') + '</div>';
            return;
        }
        allStats = d.stats || [];
        renderStats();
    } catch(e) {
        grid.innerHTML = '<div class="alert alert-danger">Netzwerkfehler: ' + e.message + '</div>';
    }
}

search.addEventListener('input', renderStats);
sortSel.addEventListener('change', renderStats);
loadStats();
setInterval(loadStats, 1000);
</script>
<?php endif; ?>
</body>
</html>
