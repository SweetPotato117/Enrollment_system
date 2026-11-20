<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../models/dbconn.php';

if (!isset($_SESSION['applicant_id'])) {
    echo json_encode(['success' => false, 'message' => 'No active session']);
    exit;
}

$id = (int)$_SESSION['applicant_id'];

$stmt = $conn->prepare('SELECT id, lrn, first_name, last_name, middle_name, email, curriculum, section_text, status FROM applicants WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$app = $res->fetch_assoc();

if (!$app) {
    echo json_encode(['success' => false, 'message' => 'Applicant not found']);
    exit;
}
// Fetch normalized curricula if the table exists
$app['curricula'] = [];
if ($conn) {
    // safe prepare — if table doesn't exist this will fail silently
    $q = $conn->prepare('SELECT curriculum FROM applicant_curricula WHERE applicant_id = ?');
    if ($q) {
        $q->bind_param('i', $id);
        $q->execute();
        $r = $q->get_result();
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $app['curricula'][] = $row['curriculum'];
            }
        }
        $q->close();
    }
}

echo json_encode(['success' => true, 'applicant' => $app]);
exit;

?>
