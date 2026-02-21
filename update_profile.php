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

$message      = '';
$message_type = 'success';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $old_password = $_POST['old_password'];

    if (password_verify($old_password, $user['password'])) {
        $password         = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        $first = $_POST['first_name'];
        $last  = $_POST['last_name'];
        $tel   = $_POST['tel'];
        $birth = $_POST['birth'];
        $break = false;

        if (!empty($password) || !empty($password_confirm)) {
            if ($password === $password_confirm) {
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['username']]);
            } else {
                $message      = "Neue Passwörter stimmen nicht überein";
                $message_type = 'danger';
                $break = true;
            }
        }

        if (!$break) {
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, tel = ?, birth = ? WHERE username = ?");
            $stmt->execute([$first, $last, $tel, $birth, $user['username']]);
            header("Location: index.php");
            exit();
        }
    } else {
        $message      = "Altes Passwort ist falsch!";
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Profil bearbeiten – Minecraft Dashboard</title>
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
                <li><a href="index.php"><i class="fa fa-home"></i> Dashboard</a></li>
                <li><a href="dashboard.php"><i class="fa fa-tachometer"></i> Live Metriken</a></li>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <li class="active"><a href="update_profile.php"><i class="fa fa-user"></i> Profil</a></li>
                <li><a href="logout.php"><i class="fa fa-sign-out"></i> Abmelden</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="page">
    <h1>Profil bearbeiten</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form class="register" method="POST">

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-lock"></i> Identität bestätigen</h3>
            </div>
            <div class="panel-body">
                <div class="form-group" style="max-width:400px;">
                    <label for="old_password">Aktuelles Passwort *</label>
                    <input type="password" class="form-control" id="old_password" name="old_password" placeholder="Dein aktuelles Passwort" required>
                    <span class="help-block">Zur Bestätigung deiner Identität erforderlich.</span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h3>Passwort ändern <small>(optional)</small></h3>
                <div class="form-group">
                    <label for="pw1">Neues Passwort</label>
                    <input type="password" class="form-control" id="pw1" name="password" placeholder="Leer lassen, um nicht zu ändern">
                </div>
                <div class="form-group">
                    <label for="pw2">Neues Passwort bestätigen</label>
                    <input type="password" class="form-control" id="pw2" name="password_confirm" placeholder="Passwort wiederholen">
                    <div id="pw_return" class="text-danger"></div>
                </div>
            </div>
            <div class="col-md-6">
                <h3>Persönliche Daten</h3>
                <div class="form-group">
                    <label for="first_name">Vorname</label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                           value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" placeholder="Vorname">
                </div>
                <div class="form-group">
                    <label for="last_name">Nachname</label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                           value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" placeholder="Nachname">
                </div>
                <div class="form-group">
                    <label for="tel">Telefonnummer</label>
                    <input type="tel" class="form-control" id="tel" name="tel"
                           value="<?php echo htmlspecialchars($user['tel'] ?? ''); ?>" placeholder="+49 ...">
                </div>
                <div class="form-group">
                    <label for="birth">Geburtsdatum</label>
                    <input type="date" class="form-control" id="birth" name="birth"
                           value="<?php echo htmlspecialchars($user['birth'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">
            <i class="fa fa-check"></i> Änderungen speichern
        </button>
        &nbsp;
        <a href="index.php" class="btn btn-default">Abbrechen</a>

    </form>
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

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
</body>
</html>
