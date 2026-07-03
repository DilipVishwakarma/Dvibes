<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$artistId = $_GET['artistId'] ?? '';
if ($artistId === '' || !is_numeric($artistId)) {
    echo json_encode([]);
    exit;
}

try {
    $songs = getSongsByArtistId($pdo, (int)$artistId, 100, 0);
    echo json_encode($songs);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
