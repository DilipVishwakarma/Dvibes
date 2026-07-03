<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

try {
    echo json_encode(getGenres($pdo));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
