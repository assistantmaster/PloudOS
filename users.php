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
$current = 'users';

// Nur Admins (perm 3) dürfen rein
if ($perm < 3) {
    header("Location: dashboard.php"); exit();
}

$msg     = '';
$msgType = 'success';

// POST-Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']     ?? '';
    $targetId   = (int)($_POST['user_id'] ?? 0);
    $targetUser = null;

    if ($targetId) {
        $s = $db->prepare("SELECT * FROM users WHERE id = ?");
        $s->execute([$targetId]);
        $targetUser = $s->fetch(PDO::FETCH_ASSOC);
    }

    if ($action === 'set_perm' && $targetUser) {
        // Eigene Rechte nicht verändern
        if ($targetUser['username'] === $user['username']) {
            $msg = 'Du kannst deine eigenen Berechtigungen nicht ändern.';
            $msgType = 'danger';
        } else {
            $newPerm = max(0, min(3, (int)($_POST['new_perm'] ?? 0)));
            $db->prepare("UPDATE users SET permissions = ? WHERE id = ?")->execute([$newPerm, $targetId]);
            $msg = 'Berechtigung von <strong>' . htmlspecialchars($targetUser['username']) . '</strong> auf ' . $newPerm . ' gesetzt.';
        }
    } elseif ($action === 'delete' && $targetUser) {
        if ($targetUser['username'] === $user['username']) {
            $msg = 'Du kannst deinen eigenen Account nicht löschen.';
            $msgType = 'danger';
        } else {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
            $msg = 'Benutzer <strong>' . htmlspecialchars($targetUser['username']) . '</strong> wurde gelöscht.';
        }
    } elseif ($action === 'reset_password' && $targetUser) {
        $newPassword = $_POST['new_password'] ?? '';
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $targetId]);
        $msg = 'Passwort von <strong>' . htmlspecialchars($targetUser['username']) . '</strong> wurde zurückgesetzt.';
    }
}

// Alle Benutzer laden
$allUsers = $db->query("SELECT id, username, email, permissions, created_at FROM users ORDER BY permissions DESC, username ASC")->fetchAll(PDO::FETCH_ASSOC);

$permLabels = [
    1 => ['label' => 'Zuschauer', 'class' => 'info'],
    2 => ['label' => 'Spieler', 'class' => 'warning'],
    3 => ['label' => 'Admin',     'class' => 'danger'],
    4 => ['label' => 'Owner',     'class' => 'danger'],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Benutzerverwaltung | Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
    <link rel="stylesheet" href="assets/css/panel.css">
    <link rel="icon" type="image/png" href="./assets/PloudOS-32x32.png">
    <style>
        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th {
            background: #f5f5f5; padding: 10px 12px;
            font-size: 12px; text-transform: uppercase;
            color: #888; border-bottom: 2px solid #e0e0e0;
            font-weight: 700; text-align: left;
        }
        .user-table td {
            padding: 10px 12px; border-bottom: 1px solid #f0f0f0;
            vertical-align: middle; font-size: 14px;
        }
        .user-table tr:last-child td { border-bottom: none; }
        .user-table tr:hover td { background: #fafafa; }
        .user-table tr.is-self td { background: #f0f5ff; }
        .perm-badge {
            display: inline-block; padding: 2px 10px;
            border-radius: 10px; font-size: 11px; font-weight: 700;
        }
        .perm-0 { background:#eee; color:#888; }
        .perm-1 { background:#d9edf7; color:#31708f; }
        .perm-2 { background:#fcf8e3; color:#8a6d3b; }
        .perm-3 { background:#f2dede; color:#a94442; }
        .action-btns { display: flex; gap: 6px; flex-wrap: wrap; }
        .modal-sm { max-width: 400px; }
        .stat-cards { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
        .stat-card {
            flex: 1; min-width: 130px;
            background: #fff; border: 1px solid #e0e0e0;
            border-top: 3px solid #4673E2;
            padding: 14px 16px; text-align: center;
        }
        .stat-card .num { font-size: 28px; font-weight: 700; color: #333; }
        .stat-card .lbl { font-size: 11px; color: #aaa; text-transform: uppercase; margin-top: 2px; }
    </style>
</head>
<body class="panel-layout">
<?php include 'nav_helper.php'; ?>
<div class="panel-wrapper">
    <div class="panel-content">
        <div class="page-header"><h2>Benutzerverwaltung</h2></div>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
        <?php endif; ?>

        <!-- Statistik-Karten -->
        <?php
        $counts = array_fill(0, 4, 0);
        foreach ($allUsers as $u) $counts[(int)$u['permissions']]++;
        ?>
        <div class="stat-cards">
            <div class="stat-card">
                <div class="num"><?= count($allUsers) ?></div>
                <div class="lbl">Gesamt</div>
            </div>
            <div class="stat-card" style="border-top-color:#e74c3c;">
                <div class="num"><?= $counts[3] ?></div>
                <div class="lbl">Admins</div>
            </div>
            <div class="stat-card" style="border-top-color:#e67e22;">
                <div class="num"><?= $counts[2] ?></div>
                <div class="lbl">Spieler</div>
            </div>
            <div class="stat-card" style="border-top-color:#3498db;">
                <div class="num"><?= $counts[1] + $counts[0] ?></div>
                <div class="lbl">Zuschauer / Gäste</div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Alle Benutzer</h3>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Benutzername</th>
                            <th>E-Mail</th>
                            <th>Berechtigung</th>
                            <th>Registriert</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allUsers as $u):
                        $isSelf = ($u['username'] === $user['username']);
                        $p = (int)$u['permissions'];
                    ?>
                    <tr class="<?= $isSelf ? 'is-self' : '' ?>">
                        <td style="color:#ccc;font-size:11px;"><?= (int)$u['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($u['username']) ?></strong>
                            <?php if ($isSelf): ?>
                            <span style="font-size:11px;color:#4673E2;margin-left:6px;">(du)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= isset($u['email']) ? htmlspecialchars($u['email']) : '–' ?>
                        </td>
                        <td>
                            <span class="perm-badge perm-<?= $p ?>">
                                <?= $permLabels[$p]['label'] ?? 'Unbekannt' ?> (<?= $p ?>)
                            </span>
                        </td>
                        <td style="color:#888;font-size:12px;">
                            <?= isset($u['created_at']) ? htmlspecialchars($u['created_at']) : '–' ?>
                        </td>
                        <td>
                            <?php if (!$isSelf): ?>
                            <div class="action-btns">
                                <!-- Berechtigung ändern -->
                                <button class="btn btn-xs btn-default"
                                        onclick="openPermModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', <?= $p ?>)">
                                    <i class="fa fa-shield"></i> Perm
                                </button>
                                <!-- Passwort zurücksetzen -->
                                <button class="btn btn-xs btn-warning"
                                        onclick="openPwModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                    <i class="fa fa-key"></i> Passwort
                                </button>
                                <!-- Löschen -->
                                <button class="btn btn-xs btn-danger"
                                        onclick="openDelModal(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <span style="color:#bbb;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Legende -->
        <div style="font-size:12px;color:#aaa;margin-top:8px;">
            <strong>Berechtigungsstufen:</strong>
            1 = Metriken lesen &nbsp;|&nbsp;
            2 = Spieler-Stats &nbsp;|&nbsp;
            3 = Voller Admin-Zugriff
        </div>
    </div>
</div>

<!-- Modal: Berechtigung ändern -->
<div class="modal fade" id="permModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action"  value="set_perm">
                <input type="hidden" name="user_id" id="perm-uid">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-shield"></i> Berechtigung ändern</h4>
                </div>
                <div class="modal-body">
                    <p>Benutzer: <strong id="perm-uname"></strong></p>
                    <div class="form-group">
                        <label>Neue Berechtigung</label>
                        <select name="new_perm" id="perm-sel" class="form-control">
                            <option value="1">1 – Zuschauer (Metriken)</option>
                            <option value="2">2 – Spieler (+ Stats)</option>
                            <option value="3">3 – Admin (voller Zugriff)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Passwort zurücksetzen -->
<div class="modal fade" id="pwModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action"  value="reset_password">
                <input type="hidden" name="user_id" id="pw-uid">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-key"></i> Passwort zurücksetzen</h4>
                </div>
                <div class="modal-body">
                    <p>Benutzer: <strong id="pw-uname"></strong></p>
                    <div class="form-group">
                        <label>Neues Passwort</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Passwort">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-warning">Zurücksetzen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Benutzer löschen -->
<div class="modal fade" id="delModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="user_id" id="del-uid">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" style="color:#e74c3c;"><i class="fa fa-trash"></i> Benutzer löschen</h4>
                </div>
                <div class="modal-body">
                    <p>Soll <strong id="del-uname"></strong> wirklich dauerhaft gelöscht werden?</p>
                    <p class="text-danger" style="font-size:12px;">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </div>
            </form>
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
function openPermModal(uid, uname, curPerm) {
    document.getElementById('perm-uid').value   = uid;
    document.getElementById('perm-uname').textContent = uname;
    document.getElementById('perm-sel').value   = curPerm;
    $('#permModal').modal('show');
}
function openPwModal(uid, uname) {
    document.getElementById('pw-uid').value   = uid;
    document.getElementById('pw-uname').textContent = uname;
    $('#pwModal').modal('show');
}
function openDelModal(uid, uname) {
    document.getElementById('del-uid').value  = uid;
    document.getElementById('del-uname').textContent = uname;
    $('#delModal').modal('show');
}
</script>
</body>
</html>
