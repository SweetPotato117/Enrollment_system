<?php
// controllers/section_lookup.php
// Accepts JSON POST { curriculum, section_name }
// Returns { success: true, id: <section_id> }

require_once __DIR__ . '/../models/dbconn.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$curr = isset($input['curriculum']) ? trim($input['curriculum']) : '';
$name = isset($input['section_name']) ? trim($input['section_name']) : '';

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Missing section_name']);
    exit;
}

// try to find existing section by exact display_name or by code
$sql = "SELECT id FROM sections WHERE curriculum = ? AND (LOWER(display_name) = LOWER(?) OR LOWER(code) = LOWER(?)) LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB prepare failed']); exit; }
$codeCandidate = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($name));
$stmt->bind_param('sss', $curr, $name, $codeCandidate);
$stmt->execute();
$res = $stmt->get_result();
if ($res && ($row = $res->fetch_assoc())) {
    echo json_encode(['success'=>true, 'id'=>intval($row['id'])]);
    exit;
}

// Insert new section (generate unique code)
$codeBase = $codeCandidate ?: uniqid('sec-');
$code = $codeBase;
$i = 1;
while (true) {
    // check uniqueness of code
    $s2 = $conn->prepare('SELECT id FROM sections WHERE code = ? LIMIT 1');
    if (!$s2) { echo json_encode(['success'=>false,'message'=>'DB error']); exit; }
    $s2->bind_param('s', $code);
    $s2->execute();
    $r2 = $s2->get_result();
    if ($r2 && !$r2->fetch_assoc()) break;
    $code = $codeBase . '-' . $i; $i++;
}

$ins = $conn->prepare('INSERT INTO sections (curriculum, code, display_name) VALUES (?, ?, ?)');
if (!$ins) { echo json_encode(['success'=>false,'message'=>'DB insert prepare failed']); exit; }
$ins->bind_param('sss', $curr, $code, $name);
$ok = $ins->execute();
if (!$ok) { echo json_encode(['success'=>false,'message'=>'Insert failed']); exit; }
$newId = $conn->insert_id;
echo json_encode(['success'=>true, 'id'=>intval($newId)]);
exit;
