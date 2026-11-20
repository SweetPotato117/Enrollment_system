<?php
// controllers/get_applicant.php
// Returns applicant data and uploaded document paths as JSON

require_once __DIR__ . '/../models/dbconn.php';
require_once __DIR__ . '/../models/functions.php';

// Ensure we always return JSON — convert PHP errors/notices to JSON responses
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid id']);
        exit;
    }

    $applicant = getRecord('applicants', 'id = ' . $id);
    if (!$applicant) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Applicant not found']);
        exit;
    }

    // New: uploads are stored directly under uploads/applicants/ with filenames including applicant id
    $files = [
        'reportCard' => null,
        'moralCharacter' => null,
        'healthDeclaration' => null
    ];

    // central uploads folder
    $central = __DIR__ . '/../uploads/applicants/';

    // first check for metadata_<id>.json in central folder
    if (file_exists($central . 'metadata_' . $id . '.json')) {
        $meta = json_decode(file_get_contents($central . 'metadata_' . $id . '.json'), true);
        if (is_array($meta)) {
            if (isset($meta['reportCard'])) $files['reportCard'] = $meta['reportCard'];
            if (isset($meta['moralCharacter'])) $files['moralCharacter'] = $meta['moralCharacter'];
            if (isset($meta['healthDeclaration'])) $files['healthDeclaration'] = $meta['healthDeclaration'];
            if (isset($meta['report_card'])) $files['reportCard'] = $meta['report_card'];
            if (isset($meta['good_moral'])) $files['moralCharacter'] = $meta['good_moral'];
            if (isset($meta['health_declaration'])) $files['healthDeclaration'] = $meta['health_declaration'];
        }
    } else {
        // fall back: scan central folder for files with applicant_<id>_ prefix
        if (is_dir($central)) {
            $entries = scandir($central);
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                $lower = strtolower($e);
                if (strpos($lower, 'applicant_' . $id . '_') === 0) {
                    if (strpos($lower, 'report') !== false) $files['reportCard'] = 'uploads/applicants/' . $e;
                    if (strpos($lower, 'moral') !== false) $files['moralCharacter'] = 'uploads/applicants/' . $e;
                    if (strpos($lower, 'health') !== false) $files['healthDeclaration'] = 'uploads/applicants/' . $e;
                }
            }
        }
    }

    // Build response using per-document columns on the applicants table
    $documents = [
        'report_card' => [
            'status' => isset($applicant['report_card_status']) ? $applicant['report_card_status'] : 'pending',
            'remark' => isset($applicant['report_card_remark']) ? $applicant['report_card_remark'] : null
        ],
        'good_moral' => [
            'status' => isset($applicant['moral_status']) ? $applicant['moral_status'] : 'pending',
            'remark' => isset($applicant['moral_remark']) ? $applicant['moral_remark'] : null
        ],
        'health_declaration' => [
            'status' => isset($applicant['health_status']) ? $applicant['health_status'] : 'pending',
            'remark' => isset($applicant['health_remark']) ? $applicant['health_remark'] : null
        ]
    ];

    $response = [
        'success' => true,
        'applicant' => $applicant,
        'files' => $files,
        'documents' => $documents
    ];

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
    exit;
}
