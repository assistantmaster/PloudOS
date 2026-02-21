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
$current = 'plugins';

$pluginsDir = '/home/timo/scoutsmp/plugins';
$jars = [];
if (is_dir($pluginsDir)) {
    $files = glob($pluginsDir . '/*.jar');
    if ($files) {
        foreach ($files as $f) {
            $jars[] = [
                'name' => basename($f, '.jar'),
                'file' => basename($f),
                'size' => filesize($f),
            ];
        }
        usort($jars, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    }
}

function formatBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1024, 1) . ' KB';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Plugins | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        .plugin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }
        .plugin-card {
            background: #fff;
            border: 1px solid #ddd;
            border-left: 4px solid #4673E2;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .plugin-icon {
            width: 36px; height: 36px; background: #4673E2;
            border-radius: 4px; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
        }
        .plugin-icon i { color: #fff; font-size: 16px; }
        .plugin-name { font-weight: 700; font-size: 13px; color: #333; word-break: break-all; }
        .plugin-file { font-size: 11px; color: #aaa; margin-top: 2px; }
        .plugin-size { font-size: 11px; color: #888; margin-top: 2px; }
        .plugin-count { color: #888; font-size: 13px; margin-bottom: 16px; }
    </style>
</head>
<body class="panel-layout">
<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar">
                <span class="sr-only">Navigation umschalten</span>
                <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.php"><img alt="Logo" height="50" src="assets/PloudOS-Small.png"></a>
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
            <li><a href="dashboard.php">Übersicht</a></li>
            <li><a href="console.php">Konsole</a></li>
            <li><a href="config.php">Konfiguration</a></li>
            <li class="active"><a href="plugins.php">Plugins</a></li>
            <li><a href="stats.php">Player-Stats</a></li>
            <li><a href="bluemap.php">BlueMap</a></li>
            <li><a href="screenshots.php">Screenshots &amp; Waypoints</a></li>
            <li><a href="players.php">Spieler</a></li>
        </ul>
    </div>
    <div class="panel-content">
        <div class="page-header">
            <h2>Plugins</h2>
        </div>

        <?php if (empty($jars)): ?>
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            Keine Plugins gefunden in <code><?php echo htmlspecialchars($pluginsDir); ?></code>
        </div>
        <?php else: ?>
        <div class="plugin-count">
            <i class="fa fa-puzzle-piece"></i> <?php echo count($jars); ?> Plugin(s) installiert
        </div>
        <div class="plugin-grid">
            <?php foreach ($jars as $p): ?>
            <div class="plugin-card">
                <div class="plugin-icon"><i class="fa fa-puzzle-piece"></i></div>
                <div>
                    <div class="plugin-name"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="plugin-file"><?php echo htmlspecialchars($p['file']); ?></div>
                    <div class="plugin-size"><?php echo formatBytes($p['size']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
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
</body>
</html>
