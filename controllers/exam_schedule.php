<?php
// controllers/exam_schedule.php
// Simple REST-ish endpoint for exam schedules: GET (list), POST (create), PUT/PATCH (update), DELETE (delete).

// hide HTML error output (avoid sending HTML to client) and log instead
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// start output buffering to capture stray output (will be logged not sent)
ob_start();

function _exam_log($msg) {
    @file_put_contents(__DIR__ . '/exam_debug.log', "[".date('c')."] " . $msg . "\n", FILE_APPEND | LOCK_EX);
}

set_error_handler(function($severity, $message, $file, $line){
    _exam_log("PHP_ERROR: {$message} in {$file}:{$line}");
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','detail'=>$message]);
    exit;
});

set_exception_handler(function($ex){
    _exam_log("PHP_EXCEPTION: " . $ex->getMessage() . "\n" . $ex->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server exception','detail'=>$ex->getMessage()]);
    exit;
});

require_once __DIR__ . '/../models/dbconn.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];



// Convenience: read JSON body for POST/PUT/PATCH/DELETE
$raw = file_get_contents('php://input');
$body = null;
if ($raw) {
    $body = json_decode($raw, true);
    // debug log incoming body for troubleshooting
    @file_put_contents(__DIR__ . '/exam_debug.log', "[".date('c')."] RAW: " . $raw . PHP_EOL, FILE_APPEND | LOCK_EX);
    if (json_last_error() !== JSON_ERROR_NONE) {
        @file_put_contents(__DIR__ . '/exam_debug.log', "[".date('c')."] JSON_ERROR: " . json_last_error_msg() . PHP_EOL, FILE_APPEND | LOCK_EX);
    } else {
        @file_put_contents(__DIR__ . '/exam_debug.log', "[".date('c')."] PARSED: " . print_r($body, true) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if ($method === 'GET') {
    // Optional: ?id= or ?section_id= or ?curriculum=
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT es.*, s.display_name AS section_name, s.curriculum AS section_curriculum, s.code AS section_code FROM exam_schedules es LEFT JOIN sections s ON s.id = es.section_id WHERE es.id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        echo json_encode(['success' => true, 'schedule' => $row], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // list all or by section_id or by curriculum
    $section_id = 0;
    $curriculumFilter = null;
    if (isset($_GET['section_id']) && intval($_GET['section_id']) > 0) {
        $section_id = intval($_GET['section_id']);
    } elseif (isset($_GET['curriculum']) && $_GET['curriculum'] !== '') {
        // allow curriculum to be passed (e.g. ?curriculum=SPICT or spict)
        $curriculumFilter = strtolower(trim($_GET['curriculum']));
        @file_put_contents(__DIR__ . '/exam_debug.log', "[".date('c')."] CURRICULUM_FILTER: {$_GET['curriculum']} => curriculum={$curriculumFilter}\n", FILE_APPEND | LOCK_EX);
    }

    // optional exam_date filter (YYYY-MM-DD)
    $exam_date = isset($_GET['exam_date']) && $_GET['exam_date'] !== '' ? trim($_GET['exam_date']) : null;

    // Build SQL with optional filters and bind params dynamically
    $baseSql = "SELECT es.*, s.display_name AS section_name, s.curriculum AS section_curriculum, s.code AS section_code FROM exam_schedules es LEFT JOIN sections s ON s.id = es.section_id";
    $where = [];
    $params = [];
    $types = '';
    if ($section_id > 0) {
        $where[] = 'es.section_id = ?';
        $types .= 'i';
        $params[] = $section_id;
    }
    if ($curriculumFilter) {
        $where[] = 's.curriculum = ?';
        $types .= 's';
        $params[] = $curriculumFilter;
    }
    if ($exam_date) {
        $where[] = 'es.exam_date = ?';
        $types .= 's';
        $params[] = $exam_date;
    }
    $sql = $baseSql . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY es.exam_date, es.exam_time';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        @file_put_contents(__DIR__ . '/exam_debug.log', "[".date('c')."] PREPARE_FAILED: {$conn->error}\n", FILE_APPEND | LOCK_EX);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Prepare failed', 'error' => $conn->error]);
        exit;
    }
    if (!empty($params)) {
        $bind = array_merge([$types], $params);
        $refs = [];
        foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode(['success' => true, 'schedules' => $out], JSON_UNESCAPED_SLASHES);
    exit;
}

// Helper: resolve section identifier (id or code or curriculum)
function resolve_section_id($conn, $section) {
    if (!$section) return 0;
    if (is_numeric($section)) return intval($section);
    // try code first
    $stmt = $conn->prepare('SELECT id FROM sections WHERE code = ? LIMIT 1');
    $stmt->bind_param('s', $section);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && isset($r['id'])) return intval($r['id']);
    // try curriculum, pick first matching section
    $stmt2 = $conn->prepare('SELECT id FROM sections WHERE curriculum = ? LIMIT 1');
    $stmt2->bind_param('s', $section);
    $stmt2->execute();
    $r2 = $stmt2->get_result()->fetch_assoc();
    if ($r2 && isset($r2['id'])) return intval($r2['id']);
    return 0;
}

if ($method === 'POST') {
    // accept either explicit section/section_id or a curriculum string (we resolve to a section)
    $section = $body['section'] ?? ($body['section_id'] ?? null);
    $section_id = 0;
    if (isset($body['curriculum'])) {
        $curr = strtolower(trim($body['curriculum']));
        $allowed = ['spict','ste','spj','spa','ict'];
        if (!in_array($curr, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid curriculum']);
            exit;
        }
        $section_id = resolve_section_id($conn, $curr);
    } else {
        $section_id = resolve_section_id($conn, $section);
    }
    if (!$section_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or unknown section/curriculum']);
        exit;
    }
    $exam_date = $body['exam_date'] ?? null;
    $exam_time = $body['exam_time'] ?? null;
    $venue = $body['venue'] ?? null;
    $created_by = isset($body['created_by_admin_id']) ? intval($body['created_by_admin_id']) : null;
    if (!$exam_date || !$exam_time) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'exam_date and exam_time are required']);
        exit;
    }
    // Use different insert when created_by is not provided to avoid binding NULL to integer param
    if ($created_by === null) {
        $stmt = $conn->prepare('INSERT INTO exam_schedules (section_id, exam_date, exam_time, venue, created_at) VALUES (?,?,?,?,NOW())');
        if (!$stmt) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Prepare failed','error'=>$conn->error]); exit; }
        $bindOk = $stmt->bind_param('isss', $section_id, $exam_date, $exam_time, $venue);
        if (!$bindOk) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Bind failed','error'=>$conn->error]); exit; }
        $ok = $stmt->execute();
    } else {
        $stmt = $conn->prepare('INSERT INTO exam_schedules (section_id, exam_date, exam_time, venue, created_by_admin_id, created_at) VALUES (?,?,?,?,?,NOW())');
        if (!$stmt) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Prepare failed','error'=>$conn->error]); exit; }
        $bindOk = $stmt->bind_param('isssi', $section_id, $exam_date, $exam_time, $venue, $created_by);
        if (!$bindOk) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Bind failed','error'=>$conn->error]); exit; }
        $ok = $stmt->execute();
    }
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Insert failed', 'error' => $conn->error]);
        exit;
    }
    $id = $conn->insert_id;
    // return inserted row
    $stmt2 = $conn->prepare('SELECT es.*, s.display_name AS section_name FROM exam_schedules es LEFT JOIN sections s ON s.id = es.section_id WHERE es.id = ? LIMIT 1');
    $stmt2->bind_param('i', $id);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'schedule' => $row], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'PUT' || $method === 'PATCH') {
    $id = $body['id'] ?? intval($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Missing id']); exit; }
    $fields = [];
    $types = '';
    $vals = [];
    if (isset($body['section']) || isset($body['section_id']) || isset($body['curriculum'])) {
        if (isset($body['curriculum'])) {
            $curr = strtolower(trim($body['curriculum']));
            $allowed = ['spict','ste','spj','spa','ict'];
            if (!in_array($curr, $allowed, true)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid curriculum']); exit; }
            $sid = resolve_section_id($conn, $curr);
        } else {
            $sec = $body['section'] ?? $body['section_id'];
            $sid = resolve_section_id($conn, $sec);
        }
        if (!$sid) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid section/curriculum']); exit; }
        $fields[] = 'section_id = ?'; $types .= 'i'; $vals[] = $sid;
    }
    if (isset($body['exam_date'])) { $fields[]='exam_date = ?'; $types.='s'; $vals[]=$body['exam_date']; }
    if (isset($body['exam_time'])) { $fields[]='exam_time = ?'; $types.='s'; $vals[]=$body['exam_time']; }
    if (isset($body['venue'])) { $fields[]='venue = ?'; $types.='s'; $vals[]=$body['venue']; }
    if (isset($body['created_by_admin_id'])) { $fields[]='created_by_admin_id = ?'; $types.='i'; $vals[] = intval($body['created_by_admin_id']); }
    if (empty($fields)) { echo json_encode(['success'=>false,'message'=>'No fields to update']); exit; }
    $sql = 'UPDATE exam_schedules SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ? LIMIT 1';
    $types .= 'i'; $vals[] = $id;
    $stmt = $conn->prepare($sql);
    $bind = array_merge([$types], $vals);
    $tmp = [];
    foreach($bind as $k => $v) $tmp[$k] = &$bind[$k];
    call_user_func_array([$stmt, 'bind_param'], $tmp);
    $ok = $stmt->execute();
    if (!$ok) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Update failed','error'=>$conn->error]); exit; }
    $stmt2 = $conn->prepare('SELECT es.*, s.display_name AS section_name FROM exam_schedules es LEFT JOIN sections s ON s.id = es.section_id WHERE es.id = ? LIMIT 1');
    $stmt2->bind_param('i', $id);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    echo json_encode(['success'=>true,'schedule'=>$row], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'DELETE') {
    // allow id in query or body
    $id = intval($_GET['id'] ?? ($body['id'] ?? 0));
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Missing id']); exit; }
    $stmt = $conn->prepare('DELETE FROM exam_schedules WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    if (!$ok) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Delete failed']); exit; }
    echo json_encode(['success'=>true,'deleted_id'=>$id]);
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['success'=>false,'message'=>'Method not allowed']);
exit;

?>
