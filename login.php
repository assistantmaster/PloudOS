<?php
session_start();

$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = $_POST['username'];
    $pass_input = $_POST['password'];

    if (str_contains($user_input, "@")) {
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE email = ?");
        $stmt->execute([$user_input]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$user_input]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($user && password_verify($pass_input, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['logged_in'] = true;

        $stmt = $db->prepare("UPDATE users SET last_login = '" . date("Y-m-d H:i:s") . "' WHERE username = ?");
        $stmt->execute([$user['username']]);

        header("Location: dashboard.php");
        exit();
    } else {
        $message = "Benutzerdaten sind falsch!";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login – Minecraft Dashboard</title>
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/cosmo-bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/page.css">
</head>
<body>

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
                <li><a href="login.php">Anmelden</a></li>
                <li><a href="register.php">Registrieren</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="page">
    <h1>Login</h1>

    <form class="register" method="POST">

        <?php if (isset($message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="form-group">
            <label for="username">Benutzername oder E-Mail</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Benutzername oder E-Mail" required>
        </div>

        <div class="form-group">
            <label for="password">Passwort</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Passwort" required>
        </div>

        <a href="javascript:alert('Pech, nutz einen Passwortmanager!')">Passwort vergessen?</a><br><br>

        <button type="submit" class="btn btn-primary">
            <i class="fa fa-sign-in"></i> Anmelden
        </button>
        &nbsp;
        <a href="register.php" class="btn btn-default">Registrieren</a>

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
