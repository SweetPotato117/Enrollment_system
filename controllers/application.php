<?php
// controllers/application.php
// Handle application form submission: validate, insert into `applicants`, save uploaded files.

require_once __DIR__ . '/../models/dbconn.php';
require_once __DIR__ . '/../models/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

// Helper: safely fetch POST values
function val($k) {
    return isset($_POST[$k]) ? trim($_POST[$k]) : null;
}

$dob = val('dob');
$birthdate = val('birthdate');
// accept either 'sex' (from updated form) or legacy 'gender'
$gender = val('sex') ?: val('gender');
$age = val('age');
$birthplace = val('birthplace');
$religion = val('religion');
$citizenship = val('citizenship');
$school_name = val('school_name');
$school_address = val('school_address');
$first_name = val('first_name');
$middle_name = val('middle_name');
$last_name = val('last_name');
$ip = isset($_POST['ip']) ? strtolower(trim($_POST['ip'])) : 'no';
$fourps = isset($_POST['fourps']) ? strtolower(trim($_POST['fourps'])) : 'no';
$is_4ps = ($fourps === 'yes') ? 1 : 0;
$fourps_household_id = val('fourps_household_id');
$indigenous_specify = val('indigenous_specify');
$lrn = val('lrn');
$exam_date = val('exam_date');
$father_first = val('father_first');
$father_last = val('father_last');
$father_contact = val('father_contact');
$mother_first = val('mother_first');
$mother_last = val('mother_last');
$mother_contact = val('mother_contact');
$guardian_name = val('guardian_name');
$guardian_relation = val('guardian_relation');
$guardian_contact = val('guardian_contact');
$email = val('email');
$mobile = val('mobile');

// Address components (from main.html / application form)
$house_no = val('house_no');
$barangay = val('barangay');
$municipality = val('municipality');
$country = val('country');
$province = val('province');
$zip_code = val('zip_code');
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';

$sections = [];
if (isset($_POST['sections']) && is_array($_POST['sections'])) {
    $sections = array_map('trim', $_POST['sections']);
}

// Basic validation: require first and last name, password and confirmation
if (!$first_name || !$last_name || !$password) {
    http_response_code(400);
    echo 'Missing required fields: first name, last name, or password.';
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo 'Passwords do not match.';
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo 'Password must be at least 8 characters.';
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Map form fields to DB column names that exist in your current `applicants` table
$curriculum = null;
// DB enum values: regular, first, spict, ste, spj, spa, ict
$allowedCurr = ['regular','first','spict','ste','spj','spa','ict'];
if (!empty($sections) && is_array($sections)) {
    // prefer the first checked as the curriculum; fallback to 'regular'
    $candidate = strtolower($sections[0]);
    $curriculum = in_array($candidate, $allowedCurr, true) ? $candidate : 'regular';
}

$data = [
    'birthdate' => $birthdate ?: $dob,
    'gender' => $gender,
    'citizenship' => $citizenship,
    // map school_* -> current_school / current_school_address
    'current_school' => $school_name,
    'current_school_address' => $school_address,
    'first_name' => $first_name,
    'middle_name' => $middle_name,
    'last_name' => $last_name,
    'age' => $age,
    // DB column is `place_of_birth` (see schema) — map form `birthplace` to it
    'place_of_birth' => $birthplace,
    'religion' => $religion,
    'lrn' => $lrn,
    'exam_date' => $exam_date,
    'email' => $email,
    'mobile' => $mobile,
    'father_first' => $father_first,
    'father_last' => $father_last,
    'father_contact' => $father_contact,
    'mother_first' => $mother_first,
    'mother_last' => $mother_last,
    'mother_contact' => $mother_contact,
    'guardian_name' => $guardian_name,
    // map guardian_relation -> guardian_relationship
    'guardian_relationship' => $guardian_relation,
    'guardian_contact' => $guardian_contact,
    'is_4ps' => $is_4ps,
    'fourps_household_id' => $fourps_household_id,
    'is_indigenous' => ($ip === 'yes') ? 1 : 0,
    'indigenous_specify' => $indigenous_specify,
    'password_hash' => $password_hash,
    'house_no' => $house_no,
    'barangay' => $barangay,
    'municipality' => $municipality,
    'country' => $country,
    'province' => $province,
    'zip_code' => $zip_code,
    'created_at' => date('Y-m-d H:i:s')
];

if ($curriculum !== null) {
    $data['curriculum'] = $curriculum;
}

// Filter $data to only columns that exist in `applicants` to avoid unknown column errors
$availableCols = [];
if (isset($conn) && $conn instanceof mysqli) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM applicants");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $availableCols[] = $row['Field'];
        }
    }
}

$filteredData = [];
if (!empty($availableCols)) {
    foreach ($data as $k => $v) {
        if (in_array($k, $availableCols, true)) {
            $filteredData[$k] = $v;
        }
    }
} else {
    // If we couldn't fetch columns, fall back to original data (risking DB error)
    $filteredData = $data;
}

if (empty($filteredData)) {
    http_response_code(500);
    echo 'No valid applicant columns available for insert.';
    exit;
}

// Insert record
$inserted = insertRecord('applicants', $filteredData);

// Determine insert id using the active connection
$insertId = null;
if (isset($conn) && $conn instanceof mysqli) {
    $insertId = mysqli_insert_id($conn);
} elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
    $insertId = mysqli_insert_id($GLOBALS['conn']);
}

if ($inserted === false) {
    http_response_code(500);
    echo 'Failed to save application.';
    exit;
}

// Handle file uploads: save directly to uploads/applicants/ (no per-id subfolder)
if ($insertId) {
    $centralUploadDir = __DIR__ . '/../uploads/applicants/';
    if (!is_dir($centralUploadDir)) {
        @mkdir($centralUploadDir, 0755, true);
    }

    $filesMap = [
        'reportCard' => 'report_card',
        'moralCharacter' => 'good_moral',
        'healthDeclaration' => 'health_declaration'
    ];

    $savedFiles = [];
    foreach ($filesMap as $field => $destPrefix) {
        if (!empty($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES[$field]['tmp_name'];
            $orig = basename($_FILES[$field]['name']);
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            // include applicant id to keep files unique and discoverable
            $safeName = 'applicant_' . intval($insertId) . '_' . $destPrefix . '_' . time() . ($ext ? '.' . $ext : '');
            $dest = $centralUploadDir . $safeName;
            if (@move_uploaded_file($tmp, $dest)) {
                // store web-accessible relative path
                $relPath = 'uploads/applicants/' . $safeName;
                $savedFiles[$field] = $relPath;
            }
        }
    }

    // Write a metadata file for this applicant in the central folder: metadata_<id>.json
    if (!empty($savedFiles)) {
        $metaPath = $centralUploadDir . 'metadata_' . intval($insertId) . '.json';
        @file_put_contents($metaPath, json_encode($savedFiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // Persist selected curricula to normalized join table `applicant_curricula` (if available)
    if (!empty($sections) && is_array($sections)) {
        // prepare insert (use INSERT IGNORE semantics via duplicate key on table)
        if ($conn) {
            $stmtCurr = $conn->prepare("INSERT IGNORE INTO applicant_curricula (applicant_id, curriculum) VALUES (?, ?)");
            if ($stmtCurr) {
                foreach ($sections as $s) {
                    $val = strtolower(trim($s));
                    if ($val === '') continue;
                    $stmtCurr->bind_param('is', $insertId, $val);
                    @$stmtCurr->execute();
                }
                $stmtCurr->close();
            }
        }
    }
}

// Send account email (email + plaintext password) to applicant if email present
try {
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // include mailer helper in same controllers directory
        require_once __DIR__ . '/mailer.php';

        $displayName = trim(($first_name . ' ' . $last_name));
        // Point login link to the public index.html at the application root
        $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname(dirname($_SERVER['REQUEST_URI'])) . '/index.html';

        $subject = 'BNHS Enrollment — Your account details';

        // Credentials: LRN (username) + password
        $html = "<p>Good day, " . htmlspecialchars($displayName) . "</p>" .
            "<p>Your application has been received. Use the credentials below to sign in to the enrollment portal:</p>" .
            "<ul>" .
            "<li><strong>LRN (username):</strong> " . htmlspecialchars($lrn) . "</li>" .
            "<li><strong>Password:</strong> " . htmlspecialchars($password) . "</li>" .
            "</ul>" .
            "<p>Please change your password after first login for security.</p>" .
            "<p>Login here: <a href=\"" . htmlspecialchars($loginUrl) . "\">Enrollment Portal</a></p>" .
            "<p>If you did not apply, please ignore this message.</p>";

        $plain = "Good day, " . $displayName . "\n\n" .
            "Your application has been received. Use the credentials below to sign in to the enrollment portal:\n" .
            "LRN (username): " . $lrn . "\n" .
            "Password: " . $password . "\n\n" .
            "Please change your password after first login for security.\n" .
            "Login: " . $loginUrl . "\n\n" .
            "If you did not apply, please ignore this message.";

        // call mailer and log failures (do not interrupt user flow)
        try {
            $sent = send_email($email, $displayName ?: $email, $html, $plain, $subject);
            if (!$sent) {
                $msg = '[' . date('Y-m-d H:i:s') . "] Failed to send account email to: $email\n";
                @file_put_contents(__DIR__ . '/mail_error.log', $msg, FILE_APPEND | LOCK_EX);
            }
        } catch (Exception $ex) {
            $msg = '[' . date('Y-m-d H:i:s') . "] Mail exception while sending to: $email - " . $ex->getMessage() . "\n";
            @file_put_contents(__DIR__ . '/mail_error.log', $msg, FILE_APPEND | LOCK_EX);
        }
    }
} catch (Exception $e) {
    // keep going even if mail preparation fails
}

// Redirect back to applicants page with a success flag
header('Location: ../form/applicants.html?submitted=1');
exit;
