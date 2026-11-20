<?php
header('Content-Type: application/json; charset=utf-8');
// Lightweight endpoint to verify controllers are reachable from the browser
echo json_encode(['success' => true, 'time' => date('c'), 'file' => basename(__FILE__)]);
exit;

?>