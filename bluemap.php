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
$current = 'bluemap';

// BlueMap URL anpassen (Standard: Port 8100)
$bluemapUrl = 'http://192.168.178.106:8100';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>BlueMap | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        .bluemap-wrapper {
            position: relative;
            width: 100%;
            height: calc(100vh - 80px - 40px - 60px - 52px);
            min-height: 400px;
            border: 1px solid #ddd;
            border-radius: 2px;
            overflow: hidden;
            background: #1a1a1a;
        }
        .bluemap-wrapper iframe {
            width: 100%; height: 100%; border: none; display: block;
        }
        .bluemap-error {
            position: absolute; inset: 0; display: flex;
            flex-direction: column; align-items: center; justify-content: center;
            color: #888; font-size: 14px; text-align: center; gap: 8px;
        }
        .bluemap-toolbar {
            display: flex; gap: 8px; align-items: center; margin-bottom: 10px;
        }
        .bluemap-url-input {
            flex: 1; font-size: 12px; font-family: monospace;
            border: 1px solid #ddd; padding: 4px 8px; color: #555;
        }
    </style>
</head>
<body class="panel-layout">
<?php include 'nav_helper.php'; ?>
<div class="panel-wrapper">
    <div class="panel-content">
        <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h2>BlueMap</h2>
            <a href="<?= htmlspecialchars($bluemapUrl) ?>" target="_blank" class="btn btn-default btn-sm">
                <i class="fa fa-external-link"></i> In neuem Tab öffnen
            </a>
        </div>

        <div class="bluemap-wrapper" id="bm-wrap">
            <iframe id="bluemap-frame"
                    src="<?= htmlspecialchars($bluemapUrl) ?>"
                    title="BlueMap"
                    allowfullscreen
                    onerror="showError()">
            </iframe>
            <div class="bluemap-error" id="bm-error" style="display:none;">
                <i class="fa fa-map-o" style="font-size:40px;opacity:.3;"></i>
                <strong>BlueMap nicht erreichbar</strong>
                <span>Stelle sicher, dass BlueMap auf Port 8100 läuft.</span>
                <a href="<?= htmlspecialchars($bluemapUrl) ?>" target="_blank" class="btn btn-default btn-sm" style="margin-top:8px;">
                    <i class="fa fa-external-link"></i> Direkt öffnen
                </a>
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
<script>
function reloadMap() {
    const f = document.getElementById('bluemap-frame');
    f.src = f.src;
    document.getElementById('bm-error').style.display = 'none';
    f.style.display = '';
}

function showError() {
    document.getElementById('bm-error').style.display = 'flex';
    document.getElementById('bluemap-frame').style.display = 'none';
}
</script>
</body>
</html>
