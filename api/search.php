<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';


$query = $_GET['q'] ?? '';
if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT 
            s.id AS song_id,
            s.title AS song_title,
            s.slug AS song_slug,
            s.file_path,
            s.thumbnail_path,
            s.duration AS duration,
            a.id AS album_id,
            a.title AS album_title,
            a.cover_image AS cover_image,
            l.name AS language_name,
            l.code AS language_code,
            r.name AS region_name,
            e.name AS era_name,
            GROUP_CONCAT(DISTINCT ar.name ORDER BY ar.name SEPARATOR ', ') AS artist_names
        FROM songs s
        LEFT JOIN albums a ON a.id = s.album_id
        LEFT JOIN languages l ON l.id = s.language_id
        LEFT JOIN eras e ON e.id = s.era_id
        LEFT JOIN song_artists sa ON sa.song_id = s.id
        LEFT JOIN artists ar ON ar.id = sa.artist_id
        LEFT JOIN regions r ON r.id = ar.region_id
        WHERE s.title LIKE :query
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT 50"
    );
    $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $songs = array_map('mapSongRowToCard', $rows);
    echo json_encode($songs);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
