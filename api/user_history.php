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
        $limit = (int)($_GET['limit'] ?? 10);
        $limit = max(1, min(50, $limit));

        $stmt = $pdo->prepare('
            SELECT
                uh.song_id,
                uh.played_at,
                uh.last_position_seconds,
                uh.duration_seconds,
                s.title,
                s.slug,
                s.file_path,
                s.thumbnail_path,
                s.duration,
                GROUP_CONCAT(DISTINCT ar.name ORDER BY ar.name SEPARATOR ", ") AS artist_names
            FROM user_listening_history uh
            JOIN songs s ON s.id = uh.song_id
            LEFT JOIN song_artists sa ON sa.song_id = s.id
            LEFT JOIN artists ar ON ar.id = sa.artist_id
            WHERE uh.user_id = :uid
            GROUP BY uh.song_id, uh.played_at, uh.last_position_seconds, uh.duration_seconds, s.title, s.slug, s.file_path, s.thumbnail_path, s.duration
            ORDER BY uh.played_at DESC
            LIMIT :lim
        ');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $out = array_map(function ($row) {
            $thumbnail = $row['thumbnail_path'] ?: 'assets/images/default-album.jpg';
            return [
                'song_id' => (int)$row['song_id'],
                'played_at' => $row['played_at'],
                'last_position_seconds' => (int)$row['last_position_seconds'],
                'duration_seconds' => (int)$row['duration_seconds'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'artists' => $row['artist_names'] ?? null,
                'thumbnail_url' => $thumbnail,
                'audio_url' => $row['file_path'] ?? '',
            ];
        }, $rows);

        echo json_encode($out);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : null;
        $pos = isset($_POST['last_position_seconds']) ? (int)$_POST['last_position_seconds'] : null;
        $duration = isset($_POST['duration_seconds']) ? (int)$_POST['duration_seconds'] : null;

        if (!$songId) {
            http_response_code(400);
            echo json_encode(['error' => 'song_id required']);
            exit;
        }

        $stmt = $pdo->prepare('
            INSERT INTO user_listening_history (user_id, song_id, last_position_seconds, duration_seconds)
            VALUES (:uid, :song_id, :pos, :dur)
        ');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':song_id', $songId, PDO::PARAM_INT);
        $stmt->bindValue(':pos', $pos, PDO::PARAM_NULL);
        $stmt->bindValue(':dur', $duration, PDO::PARAM_NULL);
        $stmt->execute();

        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
