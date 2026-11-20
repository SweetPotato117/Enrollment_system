<?php
session_start();
// Clear session data
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
// Remove keepLoggedIn cookie if present
setcookie('keepLoggedIn', '', time() - 3600, '/');

// Redirect back to the project's index (relative to /controllers/)
header('Location: ../index.html');
exit;

?>
