<?php
// controllers/update_applicant.php
// Accepts JSON POST with applicant fields and updates the corresponding applicant record.

require_once __DIR__ . '/../models/dbconn.php';

header('Content-Type: application/json; charset=utf-8');

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

$identifierId = null;
// Accept either id or lrn to identify the applicant
if (!empty($input['id'])) {
    $identifierId = intval($input['id']);
} elseif (!empty($input['lrn'])) {
    $lrn = trim($input['lrn']);
    $stmt = $conn->prepare('SELECT id FROM applicants WHERE lrn = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB error']);
        exit;
    }
    $stmt->bind_param('s', $lrn);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Applicant not found']);
        exit;
    }
    $identifierId = intval($row['id']);
}

if (!$identifierId) {
    echo json_encode(['success' => false, 'message' => 'Missing id or lrn']);
    exit;
}

// Get available columns on applicants
$available = [];
$res = mysqli_query($conn, "SHOW COLUMNS FROM applicants");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) $available[] = $r['Field'];
}

// Map common incoming fields to actual DB columns if names differ
$fieldMap = [
    'exam_remark' => ['exam_remark', 'exam_remark', 'remark', 'remarks', 'note'],
    'exam_status' => ['exam_status', 'status']
];

// copy input and map keys to existing columns where possible
$inputMapped = $input;
foreach ($fieldMap as $incoming => $candidates) {
    if (!isset($input[$incoming])) continue;
    foreach ($candidates as $cand) {
        if (in_array($cand, $available, true)) {
            // map to this candidate column
            $inputMapped[$cand] = $input[$incoming];
            // remove the original incoming key so it won't be used directly
            unset($inputMapped[$incoming]);
            break;
        }
    }
}

// Build update set from input keys that exist in available columns
$sets = [];
$values = [];
foreach ($inputMapped as $k => $v) {
    if ($k === 'id' || $k === 'lrn') continue;
    if (in_array($k, $available, true)) {
        $sets[] = "`$k` = ?";
        $values[] = $v;
    }
}

if (empty($sets)) {
    echo json_encode(['success' => false, 'message' => 'No updatable fields provided']);
    exit;
}

$sql = 'UPDATE applicants SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare update']);
    exit;
}

// bind all as strings, id as integer at the end
$types = str_repeat('s', count($values)) . 'i';
$params = $values;
$params[] = $identifierId;

// mysqli_stmt::bind_param requires variables
$bindArgs = [];
$bindArgs[] = $types;
foreach ($params as $i => $p) {
    $bindArgs[] = &$params[$i];
}

call_user_func_array([$stmt, 'bind_param'], $bindArgs);

$ok = $stmt->execute();
if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
    exit;
}

// If the incoming update included an exam status of 'passed', normalize document columns:
// set available document status columns to 'approved' and clear any per-document remarks.
$newExamStatus = null;
foreach (['exam_status','status'] as $c) {
    if (isset($inputMapped[$c])) { $newExamStatus = $inputMapped[$c]; break; }
}
if ($newExamStatus && strtolower(trim($newExamStatus)) === 'passed') {
    $docStatusCols = ['report_card_status','good_moral_status','moral_status','health_status','health_declaration_status'];
    $docRemarkCols = ['report_card_remark','report_remark','moral_remark','health_remark','health_declaration_remark'];
    $updates = [];
    foreach ($docStatusCols as $col) {
        if (in_array($col, $available, true)) $updates[] = "`$col` = 'approved'";
    }
    foreach ($docRemarkCols as $col) {
        if (in_array($col, $available, true)) $updates[] = "`$col` = NULL";
    }
    if (!empty($updates)) {
        $sql2 = 'UPDATE applicants SET ' . implode(', ', $updates) . ' WHERE id = ? LIMIT 1';
        $s3 = $conn->prepare($sql2);
        if ($s3) {
            $s3->bind_param('i', $identifierId);
            @$s3->execute();
            $s3->close();
        }
    }
}

// Return updated row
// If incoming payload included curricula (sections[] or section_text), update normalized table
$sectionsToWrite = [];
if (!empty($input['sections']) && is_array($input['sections'])) {
    foreach ($input['sections'] as $it) {
        $it = trim($it);
        if ($it !== '') $sectionsToWrite[] = strtolower($it);
    }
} elseif (!empty($input['section_text'])) {
    $st = $input['section_text'];
    // try JSON
    if (is_string($st)) {
        $decoded = json_decode($st, true);
        if (is_array($decoded)) {
            foreach ($decoded as $it) { $it = trim($it); if ($it!=='') $sectionsToWrite[] = strtolower($it); }
        } else {
            $parts = preg_split('/[,|;]+/', $st);
            foreach ($parts as $p) { $p = trim($p); if ($p !== '') $sectionsToWrite[] = strtolower($p); }
        }
    }
}

if (!empty($sectionsToWrite) && isset($conn)) {
    // remove existing curricula for this applicant and insert the provided ones
    $del = $conn->prepare('DELETE FROM applicant_curricula WHERE applicant_id = ?');
    if ($del) {
        $del->bind_param('i', $identifierId);
        @$del->execute();
        $del->close();
    }
    $ins = $conn->prepare('INSERT IGNORE INTO applicant_curricula (applicant_id, curriculum) VALUES (?, ?)');
    if ($ins) {
        foreach (array_values(array_unique($sectionsToWrite)) as $cur) {
            if ($cur === '') continue;
            $ins->bind_param('is', $identifierId, $cur);
            @$ins->execute();
        }
        $ins->close();
    }
}

$s2 = $conn->prepare('SELECT * FROM applicants WHERE id = ? LIMIT 1');
$s2->bind_param('i', $identifierId);
$s2->execute();
$res2 = $s2->get_result();
$updated = $res2->fetch_assoc();

echo json_encode(['success' => true, 'applicant' => $updated]);
exit;
