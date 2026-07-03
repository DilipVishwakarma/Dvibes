<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

try {
    // Minimal artists listing for sidebar
    $stmt = $pdo->query("SELECT id, name FROM artists ORDER BY name");
    $artists = $stmt->fetchAll();

    // Normalize for frontend (slug optional)
    $out = array_map(function ($a) {
        return [
            'id' => (int)$a['id'],
            'name' => $a['name'],
            'slug' => isset($a['slug']) ? $a['slug'] : null,
        ];
    }, $artists);

    echo json_encode($out);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
