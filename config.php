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
$current = 'config';

$configFiles = [
    'server.properties' => '/home/timo/scoutsmp/server.properties',
    'spigot.yml'        => '/home/timo/scoutsmp/spigot.yml',
    'bukkit.yml'        => '/home/timo/scoutsmp/bukkit.yml',
];

$saved = '';
$saveError = '';

// Speichern (nur perm >= 3)
if ($perm >= 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    $fileContent = $_POST['content'] ?? '';
    if (isset($configFiles[$filename])) {
        $path = $configFiles[$filename];
        if (file_put_contents($path, $fileContent) !== false) {
            $saved = $filename;
        } else {
            $saveError = 'Konnte ' . $filename . ' nicht speichern (Schreibrechte prüfen)';
        }
    }
}

$contents = [];
foreach ($configFiles as $name => $path) {
    $contents[$name] = is_readable($path)
        ? file_get_contents($path)
        : "# Datei nicht lesbar: $path";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Konfiguration | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        .cfg-tabs { display:flex; gap:0; margin-bottom:0; border-bottom:2px solid #4673E2; }
        .cfg-tab {
            padding:8px 18px; cursor:pointer; font-size:13px; font-weight:600;
            background:#f5f5f5; border:1px solid #ddd; border-bottom:none;
            color:#555; user-select:none;
        }
        .cfg-tab.active { background:#fff; color:#4673E2; border-color:#4673E2; border-bottom:2px solid #fff; margin-bottom:-2px; }
        .cfg-panel { display:none; }
        .cfg-panel.active { display:block; }
        .cfg-textarea {
            width:100%; height:560px; font-family:'Courier New',Consolas,monospace;
            font-size:12px; line-height:1.5; border:1px solid #ddd;
            padding:10px; background:#fafafa; resize:vertical; color:#333;
            border-top:none;
        }
        .cfg-textarea[readonly] { background:#f0f0f0; color:#666; cursor:default; }
        .cfg-save-bar {
            display:flex; gap:10px; align-items:center;
            padding:8px 0; border-top:1px solid #eee; margin-top:4px;
        }
    </style>
</head>
<body class="panel-layout">
<?php include 'nav_helper.php'; ?>
<div class="panel-wrapper">
    <div class="panel-content">
        <div class="page-header">
            <h2>Konfiguration</h2>
        </div>

        <?php if ($saved): ?>
        <div class="alert alert-success"><i class="fa fa-check"></i> <?= htmlspecialchars($saved) ?> gespeichert!</div>
        <?php endif; ?>
        <?php if ($saveError): ?>
        <div class="alert alert-danger"><i class="fa fa-times"></i> <?= htmlspecialchars($saveError) ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="cfg-tabs">
            <?php $first = true; foreach (array_keys($contents) as $fname): ?>
            <div class="cfg-tab <?= $first ? 'active' : '' ?>"
                 onclick="switchTab('<?= htmlspecialchars($fname) ?>')">
                <?= htmlspecialchars($fname) ?>
                <?php if ($saved === $fname): ?> <i class="fa fa-check text-success"></i><?php endif; ?>
            </div>
            <?php $first = false; endforeach; ?>
        </div>

        <!-- Panels -->
        <?php $first = true; foreach ($contents as $fname => $fcontent): ?>
        <div class="cfg-panel <?= $first ? 'active' : '' ?>" id="panel-<?= htmlspecialchars($fname) ?>">
            <form method="post">
                <input type="hidden" name="filename" value="<?= htmlspecialchars($fname) ?>">
                <textarea name="content" class="cfg-textarea"
                    <?= $perm < 3 ? 'readonly' : '' ?>
                    id="ta-<?= htmlspecialchars($fname) ?>"
                ><?= htmlspecialchars($fcontent) ?></textarea>
                <?php if ($perm >= 3): ?>
                <div class="cfg-save-bar">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-save"></i> <?= htmlspecialchars($fname) ?> speichern
                    </button>
                    <button type="button" class="btn btn-default btn-sm"
                            onclick="resetFile('<?= htmlspecialchars($fname) ?>')">
                        <i class="fa fa-undo"></i> Zurücksetzen
                    </button>
                    <span style="font-size:11px;color:#aaa;">
                        Letzte Änderung: <?= is_readable($configFiles[$fname]) ? date('d.m.Y H:i', filemtime($configFiles[$fname])) : '?' ?>
                    </span>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <?php $first = false; endforeach; ?>
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
// Originale Inhalte für Reset merken
const originals = {};
document.querySelectorAll('[id^="ta-"]').forEach(el => {
    originals[el.id] = el.value;
});

function switchTab(fname) {
    document.querySelectorAll('.cfg-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.cfg-panel').forEach(p => p.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('panel-' + fname).classList.add('active');
}

function resetFile(fname) {
    const ta = document.getElementById('ta-' + fname);
    if (ta && confirm('Alle ungespeicherten Änderungen verwerfen?')) {
        ta.value = originals['ta-' + fname];
    }
}
</script>
</body>
</html>
