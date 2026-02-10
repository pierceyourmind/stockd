<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

// Clear session data
$_SESSION = [];

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 86400,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: /auth/login.php');
exit;
