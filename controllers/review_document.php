<?php
// controllers/review_document.php
// Accepts JSON POST to set per-document status for an applicant.

require_once __DIR__ . '/../models/dbconn.php';
header('Content-Type: application/json; charset=utf-8');

// convert PHP errors to exceptions so we always return JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    session_start();
    // Require admin session
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not authorized: admin login required']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
        exit;
    }

    $applicant_id = isset($input['applicant_id']) ? intval($input['applicant_id']) : 0;
    $doc_type = isset($input['doc_type']) ? trim($input['doc_type']) : '';
    $action = isset($input['action']) ? trim($input['action']) : '';
    $remark = isset($input['remark']) ? trim($input['remark']) : null;
    $path = isset($input['path']) ? trim($input['path']) : null;
    $reviewed_by = isset($input['reviewed_by']) ? intval($input['reviewed_by']) : null;

    if (!$applicant_id || $doc_type === '' || !in_array($action, ['approve','reject','pending'], true)) {
        echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
        exit;
    }

// Normalize doc_type values
$allowed = [ 'report_card' => 'report_card', 'reportcard'=>'report_card', 'report' => 'report_card',
            'good_moral' => 'good_moral', 'moral' => 'good_moral', 'moral_character' => 'good_moral',
            'health' => 'health_declaration', 'health_declaration' => 'health_declaration', 'healthdeclaration'=>'health_declaration' ];
    $dt = strtolower(preg_replace('/[^a-z0-9_]/', '_', $doc_type));
    if (isset($allowed[$dt])) $dt = $allowed[$dt];

    // map action to status enum
    $status = ($action === 'approve') ? 'approved' : (($action === 'reject') ? 'rejected' : 'pending');

    // Map normalized doc type to applicants table columns
    $colMap = [
        'report_card' => ['status' => 'report_card_status', 'remark' => 'report_card_remark'],
        'good_moral' => ['status' => 'moral_status', 'remark' => 'moral_remark'],
        'health_declaration' => ['status' => 'health_status', 'remark' => 'health_remark']
    ];
    if (!isset($colMap[$dt])) {
        echo json_encode(['success' => false, 'message' => 'Unsupported document type']);
        exit;
    }

    $statusCol = $colMap[$dt]['status'];
    $remarkCol = $colMap[$dt]['remark'];

    // Update applicants table per-document status/remark
    $sql = "UPDATE applicants SET {$statusCol} = ?, {$remarkCol} = ? WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare failed', 'detail' => mysqli_error($conn)]);
        exit;
    }
    $bindRemark = $remark;
    $stmt->bind_param('ssi', $status, $bindRemark, $applicant_id);
    $ok = $stmt->execute();
    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'DB execute failed', 'detail' => $stmt->error]);
        exit;
    }

    // Return updated applicant row and per-document summary (read from applicants)
    $s = $conn->prepare('SELECT * FROM applicants WHERE id = ? LIMIT 1');
    if (!$s) {
        echo json_encode(['success' => false, 'message' => 'DB prepare failed (select applicant)', 'detail' => mysqli_error($conn)]);
        exit;
    }
    $s->bind_param('i', $applicant_id);
    $s->execute();
    $appRow = $s->get_result()->fetch_assoc();

    $outDocs = [
        'report_card' => [ 'status' => $appRow['report_card_status'] ?? 'pending', 'remark' => $appRow['report_card_remark'] ?? null ],
        'good_moral' => [ 'status' => $appRow['moral_status'] ?? 'pending', 'remark' => $appRow['moral_remark'] ?? null ],
        'health_declaration' => [ 'status' => $appRow['health_status'] ?? 'pending', 'remark' => $appRow['health_remark'] ?? null ]
    ];

    echo json_encode(['success' => true, 'documents' => $outDocs, 'applicant' => $appRow], JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'detail' => $e->getMessage()]);
    exit;
}
