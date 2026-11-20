<?php
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
    if ($isAjax) jsonFail('missing', 'LRN/email and password are required.', 400);
    redirectLoginFailure('missing');
}

if (strpos($input, '@') !== false && !filter_var($input, FILTER_VALIDATE_EMAIL)) {
    if ($isAjax) jsonFail('invalid_email', 'Enter a valid email address.', 400);
    redirectLoginFailure('invalid_email');
}

if (strpos($input, '@') === false && !preg_match('/^\d{12}$/', $input)) {
    if ($isAjax) jsonFail('invalid_lrn', 'LRN must be exactly 12 digits.', 400);
    redirectLoginFailure('invalid_lrn');
}

// Escape for safe query building
$escaped = mysqli_real_escape_string($conn, $input);

// Try to find applicant by LRN first, then by email as fallback
$applicant = getRecord('applicants', "lrn = '$escaped'");
if (!$applicant) {
    $applicant = getRecord('applicants', "email = '$escaped'");
}

if (!$applicant) {
    if ($isAjax) jsonFail('not_found', 'Account not found. Check your email or LRN.', 404);
    redirectLoginFailure('not_found');
}

$hash = $applicant['password_hash'] ?? ($applicant['password'] ?? '');
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
// Ensure session cookie is sent for the application root
setcookie(session_name(), session_id(), 0, '/');
$_SESSION['applicant_id'] = (int)$applicant['id'];
$_SESSION['lrn'] = $applicant['lrn'] ?? '';
$_SESSION['applicant_name'] = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
$_SESSION['applicant_email'] = $applicant['email'] ?? '';
$_SESSION['curriculum'] = $applicant['curriculum'] ?? '';
$_SESSION['section_text'] = $applicant['section_text'] ?? '';
$_SESSION['status'] = $applicant['status'] ?? '';

if ($keep) {
    setcookie('keepLoggedIn', '1', time() + 60*60*24*30, '/');
}

if ($isAjax) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => 'student/main.html']);
    exit;
}

// Redirect to the student dashboard (absolute path under app base)
header('Location: /enrollment_system/student/main.html');
exit;

?>
