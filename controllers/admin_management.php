<?php
// Unified admin management endpoint for listing, adding, and deleting admins.
// Usage:
//  - GET  /controllers/admin_management.php?action=list
//  - POST /controllers/admin_management.php?action=add    (fields: name,email,password)
//  - POST /controllers/admin_management.php?action=delete (fields: id)

require_once __DIR__ . '/../models/functions.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
	$rows = getAllRecords('admins');
	if ($rows === false) {
		http_response_code(500);
		echo json_encode(['ok' => false, 'error' => 'Failed to fetch admins']);
		exit;
	}
	echo json_encode(['ok' => true, 'data' => $rows]);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
	$name = trim($_POST['name'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$password = $_POST['password'] ?? '';

	if ($name === '' || $email === '' || $password === '') {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
		exit;
	}

	$email = filter_var($email, FILTER_SANITIZE_EMAIL);
	$hash = password_hash($password, PASSWORD_DEFAULT);

	$data = [
		'name' => $name,
		'email' => $email,
		'password_hash' => $hash,
		'created_at' => date('Y-m-d H:i:s')
	];

	$ok = insertRecord('admins', $data);
	if (!$ok) {
		http_response_code(500);
		echo json_encode(['ok' => false, 'error' => 'Failed to insert admin']);
		exit;
	}

	echo json_encode(['ok' => true]);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	if ($id <= 0) {
		http_response_code(400);
		echo json_encode(['ok' => false, 'error' => 'Invalid id']);
		exit;
	}

	$ok = deleteRecord('admins', "id = $id");
	if (!$ok) {
		http_response_code(500);
		echo json_encode(['ok' => false, 'error' => 'Failed to delete admin']);
		exit;
	}

	echo json_encode(['ok' => true]);
	exit;
}

// If no recognized action, redirect to the UI management page for convenience
header('Location: ../form/admin_management.html');
exit;

?>
