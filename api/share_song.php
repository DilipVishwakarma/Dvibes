<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$songId = isset($_REQUEST['song_id']) ? (int)$_REQUEST['song_id'] : 0;
if ($songId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'song_id required']);
    exit;
}

try {
    // Token format: base64url(4-byte songId + 10-byte truncated HMAC)
    $signature = substr(hash_hmac('sha256', (string)$songId, APP_SECRET, true), 0, 10);
    $token = rtrim(strtr(base64_encode(pack('N', $songId) . $signature), '+/', '-_'), '=');
    echo json_encode(['ok' => true, 'token' => $token]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
