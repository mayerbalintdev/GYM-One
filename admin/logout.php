<?php
session_start();

// Munkamenet törlése
$_SESSION = array();

// Ha van session cookie, töröljük
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Munkamenet befejezése
session_destroy();

// Átirányítás a bejelentkezési oldalra
header("Location: ../admin");
exit();
?>
