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
$current = 'players';
$canEdit = ($perm >= 3);

// Dateipfade
$files = [
    'whitelist' => 'D:\timot\Testserver\whitelist.json',
    'ops'       => '/home/timo/scoutsmp/ops.json',
    'bans'      => '/home/timo/scoutsmp/banned-players.json',
];

// POST-Handler: Spieler hinzufügen / entfernen (nur perm >= 3)
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $list   = $_POST['list']   ?? '';
    $name   = trim($_POST['name'] ?? '');
    $uuid   = trim($_POST['uuid'] ?? '');

    if (in_array($list, ['whitelist', 'ops', 'bans']) && $name !== '') {
        $path = $files[$list];
        $data = is_readable($path) ? json_decode(file_get_contents($path), true) : [];
        if (!is_array($data)) $data = [];

        if ($action === 'remove') {
            $data = array_values(array_filter($data, fn($p) => strtolower($p['name']) !== strtolower($name)));
        } elseif ($action === 'add') {
            // UUID via Mojang-API holen
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $mojangRaw = @file_get_contents("https://api.mojang.com/users/profiles/minecraft/$name", false, $ctx);
            $mojang = $mojangRaw ? @json_decode($mojangRaw, true) : null;

            if (isset($mojang['id']) && strlen($mojang['id']) === 32) {
                // Online-UUID mit Bindestrichen formatieren
                $id = $mojang['id'];
                $uuid = substr($id,0,8).'-'.substr($id,8,4).'-'.substr($id,12,4).'-'.substr($id,16,4).'-'.substr($id,20);
            } else {
                // Fallback: Offline-UUID (UUID v3 von "OfflinePlayer:<Name>") – Standard-MC-Offline-Modus
                $hash = md5("OfflinePlayer:" . $name);
                // Version-Bits setzen: Version 3 → 3xxx, Variant → 10xx
                $hash[12] = '3';
                $hash[16] = dechex(hexdec($hash[16]) & 0x3 | 0x8);
                $uuid = substr($hash,0,8).'-'.substr($hash,8,4).'-'.substr($hash,12,4).'-'.substr($hash,16,4).'-'.substr($hash,20);
            }
            // Doppelte vermeiden
            $exists = array_filter($data, fn($p) => strtolower($p['name']) === strtolower($name));
            if (!$exists) {
                $entry = ['uuid' => $uuid, 'name' => $name];
                if ($list === 'ops') { $entry['level'] = 4; $entry['bypassesPlayerLimit'] = false; }
                if ($list === 'bans') { $entry['created'] = date('Y-m-d H:i:s +0000'); $entry['source'] = 'Dashboard'; $entry['expires'] = 'forever'; $entry['reason'] = 'Banned by admin'; }
                $data[] = $entry;
            }
        }
        file_put_contents($path, json_encode(array_values($data), JSON_PRETTY_PRINT));
    }
    header("Location: players.php");
    exit();
}

// Daten lesen
function readPlayerList(string $path): array {
    if (!is_readable($path)) return [];
    $d = json_decode(file_get_contents($path), true);
    return is_array($d) ? $d : [];
}

$whitelist = readPlayerList($files['whitelist']);
$ops       = readPlayerList($files['ops']);
$bans      = readPlayerList($files['bans']);

function playerHead(string $name): string {
    $name = htmlspecialchars($name);
    return "<img src=\"https://mc-heads.net/avatar/{$name}/28\" width=\"28\" height=\"28\" style=\"border-radius:2px;vertical-align:middle;margin-right:8px;\" alt=\"{$name}\" onerror=\"this.src='https://mc-heads.net/avatar/Steve/28'\">";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Spieler | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        .player-sections { display: flex; gap: 20px; flex-wrap: wrap; }
        .player-section  { flex: 1; min-width: 260px; }
        .section-title {
            font-size: 18px; font-weight: 700; color: #333;
            margin: 0 0 12px; padding-bottom: 6px;
            border-bottom: 2px solid #e0e0e0;
        }
        .player-row {
            display: flex; align-items: center;
            padding: 8px 4px;
            border-bottom: 1px solid #f0f0f0;
        }
        .player-row:last-child { border-bottom: none; }
        .player-name { flex: 1; font-size: 14px; font-weight: 600; color: #333; }
        .btn-remove {
            background: #e74c3c; border: none; color: #fff;
            width: 28px; height: 28px; border-radius: 3px;
            cursor: pointer; font-size: 14px; line-height: 28px;
            text-align: center; padding: 0; flex-shrink: 0;
        }
        .btn-remove:hover { background: #c0392b; }
        .add-row {
            display: flex; gap: 8px; margin-top: 10px; align-items: center;
        }
        .add-row input {
            flex: 1; font-size: 13px; height: 32px;
            border: 1px solid #ddd; padding: 0 8px;
        }
        .btn-add {
            background: #4673E2; border: none; color: #fff;
            width: 32px; height: 32px; border-radius: 3px;
            cursor: pointer; font-size: 18px; line-height: 32px;
            text-align: center; padding: 0; flex-shrink: 0;
        }
        .btn-add:hover { background: #2c4ebf; }
        .empty-hint { color: #aaa; font-size: 13px; padding: 8px 0; }
        .ban-reason { font-size: 11px; color: #e74c3c; display: block; }
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
            <li><a href="plugins.php">Plugins</a></li>
            <li><a href="stats.php">Player-Stats</a></li>
            <li><a href="bluemap.php">BlueMap</a></li>
            <li><a href="screenshots.php">Screenshots &amp; Waypoints</a></li>
            <li class="active"><a href="players.php">Spieler</a></li>
        </ul>
    </div>
    <div class="panel-content">
        <div class="page-header">
            <h2>Spieler</h2>
        </div>

        <div class="player-sections">

            <!-- WHITELIST -->
            <div class="player-section">
                <div class="section-title">Whitelist</div>
                <?php if (empty($whitelist)): ?>
                    <div class="empty-hint">Keine Spieler auf der Whitelist.</div>
                <?php else: ?>
                <?php foreach ($whitelist as $p): ?>
                <div class="player-row">
                    <?php echo playerHead($p['name']); ?>
                    <span class="player-name"><?php echo htmlspecialchars($p['name']); ?></span>
                    <?php if ($canEdit): ?>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="list"   value="whitelist">
                        <input type="hidden" name="name"   value="<?php echo htmlspecialchars($p['name']); ?>">
                        <button type="submit" class="btn-remove" title="Entfernen"><i class="fa fa-times"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                <form method="post" class="add-row">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="list"   value="whitelist">
                    <input type="text"   name="name"   placeholder="Benutzername" required>
                    <button type="submit" class="btn-add" title="Hinzufügen"><i class="fa fa-plus"></i></button>
                </form>
                <?php endif; ?>
            </div>

            <!-- OPS -->
            <div class="player-section">
                <div class="section-title">OPs</div>
                <?php if (empty($ops)): ?>
                    <div class="empty-hint">Keine OPs konfiguriert.</div>
                <?php else: ?>
                <?php foreach ($ops as $p): ?>
                <div class="player-row">
                    <?php echo playerHead($p['name']); ?>
                    <span class="player-name"><?php echo htmlspecialchars($p['name']); ?></span>
                    <?php if ($canEdit): ?>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="list"   value="ops">
                        <input type="hidden" name="name"   value="<?php echo htmlspecialchars($p['name']); ?>">
                        <button type="submit" class="btn-remove" title="Entfernen"><i class="fa fa-times"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                <form method="post" class="add-row">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="list"   value="ops">
                    <input type="text"   name="name"   placeholder="Benutzername" required>
                    <button type="submit" class="btn-add" title="Hinzufügen"><i class="fa fa-plus"></i></button>
                </form>
                <?php endif; ?>
            </div>

            <!-- BANS -->
            <div class="player-section">
                <div class="section-title">Bans</div>
                <?php if (empty($bans)): ?>
                    <div class="empty-hint">Keine gebannten Spieler.</div>
                <?php else: ?>
                <?php foreach ($bans as $p): ?>
                <div class="player-row">
                    <?php echo playerHead($p['name']); ?>
                    <span class="player-name">
                        <?php echo htmlspecialchars($p['name']); ?>
                        <?php if (!empty($p['reason'])): ?>
                        <span class="ban-reason"><?php echo htmlspecialchars($p['reason']); ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($canEdit): ?>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="list"   value="bans">
                        <input type="hidden" name="name"   value="<?php echo htmlspecialchars($p['name']); ?>">
                        <button type="submit" class="btn-remove" title="Entbannen"><i class="fa fa-times"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                <form method="post" class="add-row">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="list"   value="bans">
                    <input type="text"   name="name"   placeholder="Benutzername" required>
                    <button type="submit" class="btn-add" title="Bannen"><i class="fa fa-plus"></i></button>
                </form>
                <?php endif; ?>
            </div>

        </div><!-- /.player-sections -->
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
