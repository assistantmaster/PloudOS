<?php
session_start();

$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    first_name TEXT,
    last_name TEXT,
    tel TEXT,
    birth DATE,
    image TEXT,
    permissions INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP)");

$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_input      = $_POST['email'];
    $user_input       = $_POST['username'];
    $password         = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email_input]);
    $user_with_email = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$user_input]);
    $user_with_username = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_with_email) {
        $message = "Fehler: E-Mail-Adresse ist schon vergeben";
    } elseif ($user_with_username) {
        $message = "Fehler: Benutzername ist schon vergeben";
    } elseif (str_contains($user_input, "@")) {
        $message = "Benutzername darf kein @ enthalten!";
    } elseif ($password !== $password_confirm) {
        $message = "Passwörter stimmen nicht überein";
    } else {
        $hash  = password_hash($password, PASSWORD_DEFAULT);
        $first = $_POST['first_name'];
        $last  = $_POST['last_name'];
        $tel   = $_POST['tel'];
        $birth = $_POST['birth'];

        $stmt = $db->prepare("INSERT INTO users (email, username, password, first_name, last_name, tel, birth, permissions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$email_input, $user_input, $hash, $first, $last, $tel, $birth, 1]);

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$user_input]);
        $user_id = $stmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['user_id']   = $user_id['id'];
        $_SESSION['username']  = $user_input;
        $_SESSION['logged_in'] = true;

        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Registrieren – Minecraft Dashboard</title>
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
    <h1>Registrieren</h1>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form class="register" method="POST">

        <div class="row">
            <div class="col-md-6">
                <h3>Account-Daten</h3>
                <div class="form-group">
                    <label for="email">E-Mail-Adresse *</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                </div>
                <div class="form-group">
                    <label for="username">Benutzername *</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Benutzername" required>
                </div>
                <div class="form-group">
                    <label for="pw1">Passwort *</label>
                    <input type="password" class="form-control" id="pw1" name="password" placeholder="Passwort" required>
                </div>
                <div class="form-group">
                    <label for="pw2">Passwort bestätigen *</label>
                    <input type="password" class="form-control" id="pw2" name="password_confirm" placeholder="Passwort wiederholen" required>
                    <div id="pw_return" class="text-danger"></div>
                </div>
            </div>
            <div class="col-md-6">
                <h3>Persönliche Daten <small>(optional)</small></h3>
                <div class="form-group">
                    <label for="first_name">Vorname</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Vorname">
                </div>
                <div class="form-group">
                    <label for="last_name">Nachname</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Nachname">
                </div>
                <div class="form-group">
                    <label for="tel">Telefonnummer</label>
                    <input type="tel" class="form-control" id="tel" name="tel" placeholder="+49 ...">
                </div>
                <div class="form-group">
                    <label for="birth">Geburtsdatum</label>
                    <input type="date" class="form-control" id="birth" name="birth">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">Account erstellen</button>
        &nbsp;
        <a href="login.php" class="btn btn-default">Zurück zum Login</a>

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
<script src="register.js"></script>
</body>
</html>
