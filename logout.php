<?php
// logout.php
require 'lib.php';

// Saare session variables clear karein
$_SESSION = array();

// Session cookie delete karein
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session destroy karein
session_destroy();

// Login page par redirect karein
header("Location: login.php");
exit;
?>