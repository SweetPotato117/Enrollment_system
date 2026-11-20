<?php
// Logout handler: destroy session and redirect to login page
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Remove any custom keep-logged cookie
setcookie('keepLoggedIn', '', time() - 3600, '/');

// Redirect to login
// Redirect back to the project's index (relative to /controllers/)
header('Location: ../index.html');
exit;

?>