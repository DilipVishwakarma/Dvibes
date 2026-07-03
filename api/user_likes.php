<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (auth_current_user_id() === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = auth_current_user_id();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $limit = (int)($_GET['limit'] ?? 20);
        $limit = max(1, min(50, $limit));

        // Return liked songs cards
        $stmt = $pdo->prepare('
            SELECT 
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
                GROUP_CONCAT(DISTINCT ar.name ORDER BY ar.name SEPARATOR ", ") AS artist_names
            FROM user_song_likes ul
            JOIN songs s ON s.id = ul.song_id
            LEFT JOIN albums a ON a.id = s.album_id
            LEFT JOIN languages l ON l.id = s.language_id
            LEFT JOIN eras e ON e.id = s.era_id
            LEFT JOIN song_artists sa ON sa.song_id = s.id
            LEFT JOIN artists ar ON ar.id = sa.artist_id
            LEFT JOIN regions r ON r.id = ar.region_id
            WHERE ul.user_id = :uid
            GROUP BY s.id
            ORDER BY ul.created_at DESC
            LIMIT :lim
        ');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        require_once __DIR__ . '/../includes/functions.php';
        echo json_encode(array_map('mapSongRowToCard', $rows));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : null;
        $like = isset($_POST['like']) ? (int)$_POST['like'] : 1;
        if (!$songId) {
            http_response_code(400);
            echo json_encode(['error' => 'song_id required']);
            exit;
        }

        if ($like === 1) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO user_song_likes (user_id, song_id) VALUES (:uid, :sid)');
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':sid', $songId, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['ok' => true, 'liked' => true]);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM user_song_likes WHERE user_id = :uid AND song_id = :sid');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':sid', $songId, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['ok' => true, 'liked' => false]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
