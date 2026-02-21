<?php
session_start();

// 1. Session-Variablen löschen
$_SESSION = [];

// 2. Das Session-Cookie löschen (für sauberen Reset im Browser)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Session zerstören
session_destroy();

// 4. Zurück zum Login
header("Location: login.php");
exit();
?>