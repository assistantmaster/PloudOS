<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$perm = (int)($user['permissions'] ?? 0);
$current = 'dashboard';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Übersicht | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
</head>
<body class="panel-layout">

<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar">
                <span class="sr-only">Navigation umschalten</span>
                <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.php">
                <img alt="Logo" height="50" src="assets/PloudOS-Small.png">
            </a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
                <li><a href="index.php">Startseite</a></li>
                <li><a href="#">Über uns</a></li>
                <li><a href="#">Twitter</a></li>
                <li><a href="#">Discord</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <li><a href="dashboard.php">Server verwalten</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <?php echo htmlspecialchars($user['username']); ?> <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="update_profile.php"><i class="fa fa-pencil"></i> Profil bearbeiten</a></li>
                        <li class="divider"></li>
                        <li><a href="logout.php"><i class="fa fa-sign-out"></i> Abmelden</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="panel-wrapper">
    <div class="sidebar">
        <ul>
            <li class="active"><a href="dashboard.php">Übersicht</a></li>
            <li><a href="console.php">Konsole</a></li>
            <li><a href="config.php">Konfiguration</a></li>
            <li><a href="plugins.php">Plugins</a></li>
            <li><a href="stats.php">Player-Stats</a></li>
            <li><a href="bluemap.php">BlueMap</a></li>
            <li><a href="screenshots.php">Screenshots &amp; Waypoints</a></li>
            <li><a href="players.php">Spieler</a></li>
        </ul>
    </div>

    <div class="panel-content">
        <div class="page-header">
            <h2>Server verwalten</h2>
        </div>

        <div class="status-section">
            <div class="status-box">
                <div class="status-box-title">Übersicht | scoutsmp.v6.rocks</div>
                <div class="status-box-body">
                    <table class="status-table">
                        <tr>
                            <td>Status</td>
                            <td>
                                <span class="badge-online" id="srv-status-online">Online</span>
                                <span class="badge-offline" id="srv-status-offline" style="display:none;">Offline</span>
                            </td>
                        </tr>
                        <tr>
                            <td>Server IP</td>
                            <td><span class="server-ip-link">scoutsmp.v6.rocks</span></td>
                        </tr>
                        <tr>
                            <td>Dyn. IP</td>
                            <td><span class="server-ip-link" id="srv-dynip">scoutsmp.v6.rocks</span></td>
                        </tr>
                        <tr>
                            <td>Version</td>
                            <td><span class="server-ip-link" id="srv-version">Paper 1.20.1</span></td>
                        </tr>
                        <tr>
                            <td>Offline in</td>
                            <td><span class="server-ip-link" id="srv-offline">∞ min, ∞ sec</span></td>
                        </tr>
                    </table>

                    <div class="action-bar">
                        <?php if ($perm >= 3): ?>
                        <button class="btn btn-danger btn-sm btn-when-online" onclick="srvAction('stop')">Stoppen</button>
                        <button class="btn btn-danger btn-sm btn-when-online" onclick="srvAction('force-stop')">Stop erzwingen</button>
                        <button class="btn btn-default btn-sm btn-when-online" onclick="srvAction('reload')">Reload</button>
                        <button class="btn btn-success btn-sm btn-when-offline" style="display:none;" onclick="srvAction('start')">Starten</button>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:12px;"><i class="fa fa-lock"></i> Keine Berechtigung für Server-Aktionen</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="status-box-body">
                    <div class="resource-label">
                        <span><i class="fa fa-microchip"></i> CPU: <span id="cpu-text">–</span></span>
                    </div>
                    <div class="resource-bar">
                        <div class="bar-fill" id="cpu-bar" style="width:0%;"></div>
                    </div>

                    <div class="resource-label">
                        <span><i class="fa fa-database"></i> RAM: <span id="ram-text">–</span></span>
                    </div>
                    <div class="resource-bar">
                        <div class="bar-fill" id="ram-bar" style="width:0%;"></div>
                    </div>

                    <div class="resource-label">
                        <span><i class="fa fa-hdd-o"></i> SSD: <span id="ssd-text">–</span></span>
                    </div>
                    <div class="resource-bar">
                        <div class="bar-fill bar-success" id="ssd-bar" style="width:0%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Live Metriken</h3>
            </div>
            <div class="panel-body">
                <input class="form-control" id="search" placeholder="Metriken filtern…" style="margin-bottom:14px; max-width:360px;">
                <div id="metrics" class="metrics-grid"></div>
            </div>
        </div>
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
<script src="dashboard.js"></script>
<script>
const canControl = <?php echo ($perm >= 3) ? 'true' : 'false'; ?>;

function setServerOnline(online) {
    document.getElementById('srv-status-online').style.display  = online ? '' : 'none';
    document.getElementById('srv-status-offline').style.display = online ? 'none' : '';
    if (canControl) {
        document.querySelectorAll('.btn-when-online').forEach(b => b.style.display = online ? '' : 'none');
        document.querySelectorAll('.btn-when-offline').forEach(b => b.style.display = online ? 'none' : '');
    }
}

function setBar(id, pct, warnAt, dangerAt) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.width = Math.min(pct, 100) + '%';
    el.className = 'bar-fill';
    if      (pct >= dangerAt) el.classList.add('bar-danger');
    else if (pct >= warnAt)   el.classList.add('bar-warning');
    else                      el.classList.add('bar-success');
}

const _origUpdate = window.update;
window.update = async function() {
    if (_origUpdate) await _origUpdate();
    try {
        const res = await fetch("metrics.php");
        if (!res.ok) { setServerOnline(false); return; }
        const data = await res.json();
        if (!data || data.length === 0) { setServerOnline(false); return; }

        let isOnline = false;
        let ramMax = 0;

        // Ersten Pass: RAM Max ermitteln
        data.forEach(m => {
            if (m.title === 'JVM RAM Max') ramMax = parseFloat(m.raw) || 0;
        });

        data.forEach(m => {
            const key = m.title.toLowerCase();
            const raw = parseFloat(m.raw) || 0;

            if (key === 'tps') {
                document.getElementById('cpu-text').textContent = 'TPS: ' + m.value;
                setBar('cpu-bar', Math.min((raw / 20) * 100, 100), 70, 90);
                if (raw > 0) isOnline = true;
            }
            if (key === 'jvm ram belegt') {
                document.getElementById('ram-text').textContent = m.value;
                const pct = ramMax > 0 ? (raw / ramMax) * 100 : 0;
                setBar('ram-bar', pct, 70, 90);
            }
            if (key === 'weltgröße (größte)') {
                document.getElementById('ssd-text').textContent = m.value;
                setBar('ssd-bar', Math.min(raw / 1e10 * 100, 100), 60, 85);
            }
            if (key.includes('version')) {
                document.getElementById('srv-version').textContent = m.value;
            }
        });

        setServerOnline(isOnline);
    } catch(e) {
        setServerOnline(false);
    }
};

async function srvAction(action) {
    if (!canControl) return;
    const labels = { stop:'Stoppen', start:'Starten', 'force-stop':'Stop erzwingen', reload:'Reload' };
    if (!confirm(labels[action] + ' – Bist du sicher?')) return;

    const msg = document.getElementById('action-msg');
    msg.style.display = '';
    msg.className = 'text-muted';
    msg.textContent = 'Sende Befehl…';

    try {
        const r = await fetch('rcon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action })
        });
        const d = await r.json();
        msg.className = d.ok ? 'text-success' : 'text-danger';
        msg.textContent = d.response || (d.ok ? 'OK' : 'Fehler');
    } catch(e) {
        msg.className = 'text-danger';
        msg.textContent = 'Netzwerkfehler: ' + e.message;
    }
}
</script>
</body>
</html>
