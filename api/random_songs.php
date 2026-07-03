<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$limit = (int)($_GET['limit'] ?? 15);
$offset = (int)($_GET['offset'] ?? 0);
$rawSeed = isset($_GET['seed']) ? (string) $_GET['seed'] : '';
// Alphanumeric seed only; empty falls back to RAND() inside getAllSongs
$seed = preg_match('/^[a-fA-F0-9]{16,64}$/', $rawSeed) ? $rawSeed : null;

try {
    // Get total count of songs
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM songs");
    $result = $countStmt->fetch();
    $total = $result['total'];

    // Same seed + offset => same page; different offsets => disjoint windows
    $songs = getAllSongs($pdo, $limit, $offset, $seed);

    echo json_encode([
        'songs' => $songs,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'seed' => $seed,
        'hasMore' => ($offset + $limit) < $total
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
