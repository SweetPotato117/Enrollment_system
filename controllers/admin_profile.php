<?php
session_start();
require_once __DIR__ . '/../models/functions.php';
header('Content-Type: application/json; charset=utf-8');

$admin_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
if (!$admin_id) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $admin = getRecord('admins', "id = $admin_id");
    if (!$admin) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Admin not found']);
        exit;
    }
    // Do not expose password hash
    unset($admin['password_hash']);
    echo json_encode(['ok' => true, 'data' => $admin]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current = $_POST['currentPassword'] ?? '';
    $new = $_POST['newPassword'] ?? '';

    if ($name === '' || $email === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Name and email are required']);
        exit;
    }

    // Fetch existing record
    $admin = getRecord('admins', "id = $admin_id");
    if (!$admin) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Admin not found']);
        exit;
    }

    $update = ['name' => $name, 'email' => filter_var($email, FILTER_SANITIZE_EMAIL)];

    // If changing password, require current password verification
    if ($new !== '') {
        $hash = $admin['password_hash'] ?? ($admin['password'] ?? '');
        $ok = false;
        if ($hash) {
            if (password_verify($current, $hash)) $ok = true;
            elseif (hash('sha256', $current) === $hash) $ok = true; // legacy
        }
        if (!$ok) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Current password is incorrect']);
            exit;
        }
        $update['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
    }

    $res = updateRecord('admins', $update, "id = $admin_id");
    if (!$res) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to update profile']);
        exit;
    }

    // update session values
    $_SESSION['admin_name'] = $name;
    $_SESSION['admin_email'] = $email;

    echo json_encode(['ok' => true]);
    exit;
}

// Unsupported method
http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
exit;

?>