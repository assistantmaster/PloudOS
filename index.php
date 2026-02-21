<?php
session_start();

$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!empty($user['first_name']) && !empty($user['last_name'])) {
    $user_mention = $user['first_name'] . " " . $user['last_name'];
} elseif (!empty($user['first_name'])) {
    $user_mention = $user['first_name'];
} elseif (!empty($user['last_name'])) {
    $user_mention = $user['last_name'];
} else {
    $user_mention = $user['username'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard – Minecraft Server</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
</head>
<body>

<nav class="navbar navbar-default navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false">
                <span class="sr-only">Navigation umschalten</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.php">
                <img alt="Logo" height="50" src="assets/PloudOS-Small.png">
            </a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
            <ul class="nav navbar-nav">
                <li class="active"><a href="index.php"><i class="fa fa-home"></i> Dashboard</a></li>
                <li><a href="dashboard.php"><i class="fa fa-tachometer"></i> Live Metriken</a></li>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <li><a href="update_profile.php"><i class="fa fa-user"></i> <?php echo htmlspecialchars($user['username']); ?></a></li>
                <li><a href="logout.php"><i class="fa fa-sign-out"></i> Abmelden</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="page">
    <h1>Dashboard</h1>
    <p class="text-muted">Willkommen zurück, <strong><?php echo htmlspecialchars($user_mention); ?></strong>!</p>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-user"></i> Account-Informationen</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped noborder" style="margin-bottom:0;">
                        <tbody>
                            <tr>
                                <td><strong>Benutzername</strong></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>E-Mail</strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                            </tr>
                            <?php if (!empty($user['first_name']) || !empty($user['last_name'])): ?>
                            <tr>
                                <td><strong>Name</strong></td>
                                <td><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($user['tel'])): ?>
                            <tr>
                                <td><strong>Telefon</strong></td>
                                <td><?php echo htmlspecialchars($user['tel']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($user['birth'])): ?>
                            <tr>
                                <td><strong>Geburtsdatum</strong></td>
                                <td><?php echo htmlspecialchars($user['birth']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Letzter Login</strong></td>
                                <td><?php echo !empty($user['last_login']) ? htmlspecialchars($user['last_login']) : '–'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Mitglied seit</strong></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-rocket"></i> Schnellzugriff</h3>
                </div>
                <div class="panel-body">
                    <a href="dashboard.php" class="btn btn-primary btn-block" style="margin-bottom:8px;">
                        <i class="fa fa-tachometer"></i> Live Metriken anzeigen
                    </a>
                    <a href="update_profile.php" class="btn btn-default btn-block" style="margin-bottom:8px;">
                        <i class="fa fa-pencil"></i> Benutzerdaten ändern
                    </a>
                    <a href="logout.php" class="btn btn-danger btn-block">
                        <i class="fa fa-sign-out"></i> Abmelden
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Server Metriken – Live Vorschau</h3>
        </div>
        <div class="panel-body">
            <input class="form-control" id="search" placeholder="Metriken filtern…" style="margin-bottom:15px; max-width:400px;">
            <div id="metrics" class="metrics-grid"></div>
        </div>
    </div>
</div>

<div class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-6">Minecraft Dashboard &copy; <?php echo date('Y'); ?></div>
            <div class="col-md-6 text-right">
                <a href="#">Datenschutz</a> | <a href="#">Impressum</a>
            </div>
        </div>
    </div>
</div>

<style>
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}
.metric {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-left: 4px solid #2780e3;
    padding: 14px 16px;
}
.metric.green { border-left-color: #3fb618; }
.metric.yellow { border-left-color: #ff7518; }
.metric.red    { border-left-color: #ff0039; }
.metric-name  { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 5px; }
.metric-value { font-size: 1.5rem; font-weight: 700; color: #333; }
</style>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="dashboard.js"></script>
<script>
    clearInterval(window._metricsInterval);
    window._metricsInterval = setInterval(update, 5000);
</script>
</body>
</html>
