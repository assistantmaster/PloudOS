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
$current = 'backups';

if ($perm < 3) {
    header("Location: dashboard.php"); exit();
}

define('BACKUP_DIR',  '/home/timo/scoutsmp/backups');
define('SERVER_DIR',  '/home/timo/scoutsmp');

// Backup-Verzeichnis anlegen falls nötig
if (!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR, 0755, true);

// Backups auflesen
function listBackups(): array {
    $files = glob(BACKUP_DIR . '/*.zip') ?: [];
    $out = [];
    foreach ($files as $f) {
        $out[] = [
            'name'    => basename($f),
            'path'    => $f,
            'size'    => filesize($f),
            'mtime'   => filemtime($f),
        ];
    }
    usort($out, fn($a, $b) => $b['mtime'] - $a['mtime']);
    return $out;
}

function fmtBytes(int $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576, 1)    . ' MB';
    return round($b / 1024, 1) . ' KB';
}

$backups = listBackups();
$totalSize = array_sum(array_column($backups, 'size'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Backups | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        .backup-table { width:100%; border-collapse:collapse; }
        .backup-table th {
            background:#f5f5f5; padding:10px 12px;
            font-size:12px; text-transform:uppercase;
            color:#888; border-bottom:2px solid #e0e0e0;
            font-weight:700; text-align:left;
        }
        .backup-table td {
            padding:10px 12px; border-bottom:1px solid #f0f0f0;
            vertical-align:middle; font-size:13px;
        }
        .backup-table tr:last-child td { border-bottom:none; }
        .backup-table tr:hover td { background:#fafafa; }
        .backup-name { font-family:'Courier New',monospace; font-size:12px; color:#333; }
        .stat-cards { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:20px; }
        .stat-card {
            flex:1; min-width:130px;
            background:#fff; border:1px solid #e0e0e0;
            border-top:3px solid #4673E2;
            padding:14px 16px; text-align:center;
        }
        .stat-card .num { font-size:26px; font-weight:700; color:#333; }
        .stat-card .lbl { font-size:11px; color:#aaa; text-transform:uppercase; margin-top:2px; }
        .create-bar {
            display:flex; gap:10px; align-items:center;
            padding:14px 0; flex-wrap:wrap;
        }
        #create-log {
            background:#1a1a1a; color:#d4d4d4;
            font-family:'Courier New',monospace; font-size:12px;
            padding:12px; min-height:60px; max-height:180px;
            overflow-y:auto; white-space:pre-wrap;
            border:1px solid #333; border-radius:2px;
            display:none; margin-top:10px;
        }
        .progress { margin:8px 0 0; height:6px; }
    </style>
</head>
<body class="panel-layout">
<?php include 'nav_helper.php'; ?>
<div class="panel-wrapper">
    <div class="panel-content">
        <div class="page-header">
            <h2><i class="fa fa-hdd-o"></i> Backups</h2>
        </div>

        <!-- Statistiken -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="num"><?= count($backups) ?></div>
                <div class="lbl">Backups</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= fmtBytes($totalSize) ?></div>
                <div class="lbl">Gesamt-Größe</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= count($backups) > 0 ? date('d.m.Y', $backups[0]['mtime']) : '–' ?></div>
                <div class="lbl">Letztes Backup</div>
            </div>
        </div>

        <!-- Backup erstellen -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-plus-circle"></i> Neues Backup erstellen</h3>
            </div>
            <div class="panel-body">
                <p style="font-size:13px;color:#666;margin-bottom:10px;">
                    Erstellt ein ZIP-Archiv des gesamten Server-Ordners (<code><?= htmlspecialchars(SERVER_DIR) ?></code>)
                    in <code><?= htmlspecialchars(BACKUP_DIR) ?></code>.
                    Der <code>backups</code>-Ordner selbst wird dabei automatisch ausgeschlossen.
                    Der Server läuft während des Backups weiter.
                </p>
                <div class="create-bar">
                    <button class="btn btn-primary" id="btn-create" onclick="startBackup()">
                        <i class="fa fa-archive"></i> Backup jetzt erstellen
                    </button>
                    <span id="create-status" style="font-size:13px;color:#888;"></span>
                </div>
                <div class="progress" id="create-progress" style="display:none;">
                    <div class="progress-bar progress-bar-striped active" style="width:100%;"></div>
                </div>
                <div id="create-log"></div>
            </div>
        </div>

        <!-- Backup-Liste -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-list"></i> Vorhandene Backups</h3>
            </div>
            <div class="panel-body" style="padding:0;">
                <?php if (empty($backups)): ?>
                <div style="padding:20px;color:#aaa;text-align:center;">
                    <i class="fa fa-inbox fa-2x"></i><br>
                    Noch keine Backups vorhanden.
                </div>
                <?php else: ?>
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>Dateiname</th>
                            <th>Größe</th>
                            <th>Erstellt am</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($backups as $bk): ?>
                    <tr>
                        <td>
                            <i class="fa fa-file-archive-o" style="color:#4673E2;margin-right:8px;"></i>
                            <span class="backup-name"><?= htmlspecialchars($bk['name']) ?></span>
                        </td>
                        <td><?= fmtBytes($bk['size']) ?></td>
                        <td style="color:#888;"><?= date('d.m.Y H:i', $bk['mtime']) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="backup_action.php?action=download&file=<?= urlencode($bk['name']) ?>"
                                   class="btn btn-xs btn-primary">
                                    <i class="fa fa-download"></i> Download
                                </a>
                                <button class="btn btn-xs btn-danger"
                                        onclick="deleteBackup('<?= htmlspecialchars($bk['name'], ENT_QUOTES) ?>')">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Backup löschen -->
<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog" style="max-width:400px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" style="color:#e74c3c;"><i class="fa fa-trash"></i> Backup löschen</h4>
            </div>
            <div class="modal-body">
                <p>Backup <strong id="del-fname"></strong> wirklich löschen?</p>
                <p class="text-danger" style="font-size:12px;">Diese Aktion kann nicht rückgängig gemacht werden.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="del-confirm">Löschen</button>
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
async function startBackup() {
    const btn      = document.getElementById('btn-create');
    const status   = document.getElementById('create-status');
    const progress = document.getElementById('create-progress');
    const log      = document.getElementById('create-log');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Backup läuft…';
    status.textContent = 'Bitte warten, das kann je nach Weltgröße einige Minuten dauern…';
    progress.style.display = '';
    log.style.display = 'block';
    log.textContent = '';

    try {
        const r = await fetch('backup_action.php?action=create', { method: 'POST' });
        const d = await r.json();
        progress.style.display = 'none';
        log.textContent = d.log || '';
        if (d.ok) {
            status.style.color = '#27ae60';
            status.textContent = '✓ Backup erstellt: ' + (d.filename || '');
            btn.innerHTML = '<i class="fa fa-archive"></i> Backup jetzt erstellen';
            btn.disabled = false;
            setTimeout(() => location.reload(), 1500);
        } else {
            status.style.color = '#e74c3c';
            status.textContent = '✗ Fehler: ' + (d.error || 'Unbekannt');
            btn.innerHTML = '<i class="fa fa-archive"></i> Backup jetzt erstellen';
            btn.disabled = false;
        }
    } catch(e) {
        progress.style.display = 'none';
        status.style.color = '#e74c3c';
        status.textContent = 'Netzwerkfehler: ' + e.message;
        btn.innerHTML = '<i class="fa fa-archive"></i> Backup jetzt erstellen';
        btn.disabled = false;
    }
}

let pendingDeleteFile = null;
function deleteBackup(fname) {
    pendingDeleteFile = fname;
    document.getElementById('del-fname').textContent = fname;
    $('#delModal').modal('show');
}

document.getElementById('del-confirm').addEventListener('click', async () => {
    if (!pendingDeleteFile) return;
    $('#delModal').modal('hide');
    try {
        const r = await fetch('backup_action.php?action=delete&file=' + encodeURIComponent(pendingDeleteFile), { method: 'POST' });
        const d = await r.json();
        if (d.ok) {
            location.reload();
        } else {
            alert('Fehler beim Löschen: ' + (d.error || 'Unbekannt'));
        }
    } catch(e) {
        alert('Netzwerkfehler: ' + e.message);
    }
});
</script>
</body>
</html>
