<?php
// Admin login handler — uses existing functions.php for DB connection/utils
session_start();
require_once __DIR__ . '/../models/functions.php';

// Detect AJAX/JSON requests so we can return JSON instead of redirecting
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

function jsonFail($reason = 'failed', $message = null, $status = 400) {
    if (!headers_sent()) header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode(['success' => false, 'reason' => $reason, 'message' => $message]);
    exit;
}

function redirectLoginFailure($reason) {
    header('Location: /enrollment_system/index.html?login=failed&reason=' . rawurlencode($reason));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /enrollment_system/index.html');
    exit;
}

$input = trim($_POST['emailOrLrn'] ?? '');
$password = $_POST['password'] ?? '';
$keep = !empty($_POST['keepLoggedIn']);

if ($input === '' || $password === '') {
    if ($isAjax) jsonFail('missing', 'Email/LRN and password are required.', 400);
    redirectLoginFailure('missing');
}

if (strpos($input, '@') !== false && !filter_var($input, FILTER_VALIDATE_EMAIL)) {
    if ($isAjax) jsonFail('invalid_email', 'Enter a valid email address.', 400);
    redirectLoginFailure('invalid_email');
}

if (strpos($input, '@') === false && !preg_match('/^\d{12}$/', $input)) {
    if ($isAjax) jsonFail('invalid_email', 'Enter a valid email address or 12-digit LRN.', 400);
    redirectLoginFailure('invalid_email');
}

// Escape input for condition (functions::getRecord expects a condition string)
$escaped = mysqli_real_escape_string($conn, $input);

// Try find by email first
$admin = getRecord('admins', "email = '$escaped'");

// If not found, no alternative lookup implemented — fail
if (!$admin) {
    if ($isAjax) jsonFail('not_found', 'Account not found. Check your email or LRN.', 404);
    redirectLoginFailure('not_found');
}

$hash = $admin['password_hash'] ?? ($admin['password'] ?? '');
$authenticated = false;
if ($hash) {
    if (password_verify($password, $hash)) {
        $authenticated = true;
    } elseif (hash('sha256', $password) === $hash) {
        // legacy SHA-256 stored hashes
        $authenticated = true;
    }
}

if (!$authenticated) {
    if ($isAjax) jsonFail('password', 'Incorrect password.', 401);
    redirectLoginFailure('password');
}

// Authenticated — create session
session_regenerate_id(true);
$_SESSION['admin_id'] = $admin['id'];
$_SESSION['admin_name'] = $admin['name'] ?? '';
$_SESSION['admin_email'] = $admin['email'] ?? '';

if ($keep) {
    setcookie('keepLoggedIn', '1', time() + 60*60*24*30, '/');
}

// Respond differently for AJAX vs normal form submit
if ($isAjax) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => 'form/dashboard.html']);
    exit;
}

header('Location: ../form/dashboard.html');
exit;

?>
