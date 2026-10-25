<?php
/**
 * Logout script
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

if (is_logged_in()) {
    log_audit('logout', 'users', get_current_user_id(), 'User logged out');
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: /MeatShop/login.php");
exit;
