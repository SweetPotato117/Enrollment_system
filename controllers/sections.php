<?php
// controllers/sections.php
// Returns a list of sections (id, curriculum, code, display_name)
require_once __DIR__ . '/../models/dbconn.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB unavailable']);
    exit;
}
$q = $conn->prepare('SELECT id, curriculum, code, display_name FROM sections ORDER BY curriculum, id');
$q->execute();
$res = $q->get_result();
$out = [];
while ($r = $res->fetch_assoc()) $out[] = $r;
echo json_encode(['success'=>true,'sections'=>$out], JSON_UNESCAPED_SLASHES);
exit;

?>
