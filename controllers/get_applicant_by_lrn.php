<?php
// controllers/get_applicant_by_lrn.php
// Returns applicant record by LRN as JSON (no DB schema changes).

require_once __DIR__ . '/../models/dbconn.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['lrn']) || trim($_GET['lrn']) === '') {
    echo json_encode(['success' => false, 'message' => 'Missing lrn parameter']);
    exit;
}

$lrn = trim($_GET['lrn']);

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM applicants WHERE lrn = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    exit;
}

$stmt->bind_param('s', $lrn);
$stmt->execute();
$res = $stmt->get_result();
$app = $res->fetch_assoc();

if (!$app) {
    echo json_encode(['success' => false, 'message' => 'Applicant not found']);
    exit;
}

// Return applicant row as-is; frontend will map fields it needs.
// Fetch applicant curricula from applicant_curricula table (if exists)
$app['curricula'] = [];
if (!empty($app['id'])) {
    $aid = (int)$app['id'];
    $qc = "SELECT curriculum FROM applicant_curricula WHERE applicant_id = $aid";
    $qr = @mysqli_query($conn, $qc);
    if ($qr) {
        while ($row = mysqli_fetch_assoc($qr)) {
            if (isset($row['curriculum'])) $app['curricula'][] = $row['curriculum'];
        }
    }
}

// Keep the `curriculum` value from the `applicants` table intact.
// Do not overwrite it with values from `applicant_curricula` — frontend expects the
// applicant row's `curriculum` to be authoritative.

echo json_encode(['success' => true, 'applicant' => $app]);
exit;
