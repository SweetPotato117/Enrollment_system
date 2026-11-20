<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!is_string($path) || !preg_match('~^/controllers/([a-z_]+)\.php$~D', $path, $match)) {
    http_response_code(404);
    exit;
}

$controller = $match[1];
$public = ['application', 'login_admin', 'logout', 'logout_admin', 'student_login',
    'student_logout', 'student_session', 'ping'];
$staff = ['admin_management', 'admin_profile', 'exam_schedule', 'get_applicant',
    'get_applicant_by_lrn', 'get_applicants', 'review_document', 'section_lookup',
    'sections', 'update_applicant'];

if (!in_array($controller, $public, true)) {
    if (!in_array($controller, $staff, true)) {
        http_response_code(404);
        exit;
    }
    session_start(['read_and_close' => true]);
    $studentId = (int) ($_SESSION['applicant_id'] ?? 0);
    $studentRead = $studentId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET' &&
        ($controller === 'exam_schedule' || ($controller === 'get_applicant' &&
        (string) $studentId === ($_GET['id'] ?? '')));
    if (empty($_SESSION['admin_id']) && !$studentRead) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Login required']);
        exit;
    }
}

chdir(__DIR__ . '/../controllers');
require __DIR__ . '/../controllers/' . $controller . '.php';
