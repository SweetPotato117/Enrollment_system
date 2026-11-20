<?php
// controllers/get_applicants.php
// Returns a list of applicants (basic fields) as JSON

require_once __DIR__ . '/../models/dbconn.php';
require_once __DIR__ . '/../models/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // limit to reasonable number to avoid huge responses
    $rows = getAllRecords('applicants', 'ORDER BY id DESC LIMIT 1000');
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)$r['id'],
            'first_name' => $r['first_name'] ?? '',
            'middle_name' => $r['middle_name'] ?? '',
            'last_name' => $r['last_name'] ?? '',
            'lrn' => $r['lrn'] ?? '',
            'curriculum' => $r['curriculum'] ?? '',
            // include year_level for client-side grade filtering
            'year_level' => $r['year_level'] ?? '',
            // section text stored in applicants (or fallback to older `section` column)
            'section_text' => $r['section_text'] ?? $r['section'] ?? '',
            'section_id' => isset($r['section_id']) ? (int)$r['section_id'] : null,
            // exam fields
            'exam_status' => $r['exam_status'] ?? ($r['status'] ?? ''),
            'exam_remark' => $r['exam_remark'] ?? ($r['remark'] ?? ''),
            // document file paths (may be stored under various column names in older schemas)
            'report_card' => $r['report_card'] ?? $r['report_card_path'] ?? '',
            'moral_character' => $r['moral_character'] ?? $r['moral'] ?? '',
            'health_declaration' => $r['health_declaration'] ?? $r['health'] ?? '',
            // document status and remark columns (if present)
            'report_card_status' => $r['report_card_status'] ?? $r['report_card_state'] ?? '',
            'report_card_remark' => $r['report_card_remark'] ?? $r['report_remark'] ?? '',
            'good_moral_status' => $r['good_moral_status'] ?? $r['moral_status'] ?? '',
            'moral_remark' => $r['moral_remark'] ?? '',
            'health_status' => $r['health_status'] ?? $r['health_declaration_status'] ?? '',
            'health_remark' => $r['health_remark'] ?? $r['health_declaration_remark'] ?? ''
        ];
    }
    echo json_encode(['success' => true, 'applicants' => $out], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
