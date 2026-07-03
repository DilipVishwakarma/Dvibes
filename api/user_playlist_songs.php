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

// This endpoint assumes a table named:
// user_playlist_songs(playlist_id INT, user_id INT, song_id INT, created_at ...)
// If your DB uses a different table name/schema, adjust the SQL below.

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $playlistId = isset($_GET['playlist_id']) ? (int)$_GET['playlist_id'] : 0;
        if ($playlistId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'playlist_id required']);
            exit;
        }

        $ownerCheck = $pdo->prepare('SELECT id FROM user_playlists WHERE id = :pid AND user_id = :uid LIMIT 1');
        $ownerCheck->bindValue(':pid', $playlistId, PDO::PARAM_INT);
        $ownerCheck->bindValue(':uid', $userId, PDO::PARAM_INT);
        $ownerCheck->execute();
        if (!$ownerCheck->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not own this playlist']);
            exit;
        }

        $stmt = $pdo->prepare(
            "SELECT 
                ups.song_id as id,
                s.title,
                s.slug,
                s.file_path,
                s.thumbnail_path,
                s.duration,
                GROUP_CONCAT(DISTINCT ar.name ORDER BY ar.name SEPARATOR ', ') AS artist_names
            FROM user_playlist_songs ups
            JOIN songs s ON s.id = ups.song_id
            LEFT JOIN song_artists sa ON sa.song_id = s.id
            LEFT JOIN artists ar ON ar.id = sa.artist_id
            WHERE ups.playlist_id = :pid
            GROUP BY ups.song_id
            ORDER BY ups.added_at DESC"
        );
        $stmt->bindValue(':pid', $playlistId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        // Map to the same card shape expected by the frontend/player.
        // Reuse minimal mapping (thumbnail/audio paths) similar to mapSongRowToCard.
        $out = array_map(function ($row) {
            $thumb = $row['thumbnail_path'] ?? null;
            if (empty($thumb)) {
                $thumb = '';
            }

            $thumbnailUrl = !empty($thumb) ? $thumb : 'assets/images/default-album.jpg';
            $audioUrl = !empty($row['file_path']) ? $row['file_path'] : '';

            return [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'artists' => $row['artist_names'] ?? null,
                'duration' => isset($row['duration']) ? (int)$row['duration'] : null,
                'thumbnail_url' => $thumbnailUrl,
                'audio_url' => $audioUrl,
            ];
        }, $rows);

        echo json_encode($out);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $playlistId = isset($_POST['playlist_id']) ? (int)$_POST['playlist_id'] : 0;
            $songIdsRaw = $_POST['song_ids'] ?? [];

            // Accept either JSON string, comma-separated, or repeated fields.
            $songIds = [];
            if (is_string($songIdsRaw)) {
                $decoded = json_decode($songIdsRaw, true);
                if (is_array($decoded)) {
                    $songIds = $decoded;
                } else {
                    $songIds = array_filter(array_map('trim', explode(',', $songIdsRaw)));
                }
            } elseif (is_array($songIdsRaw)) {
                $songIds = $songIdsRaw;
            }

            $songIds = array_values(array_filter(array_map(fn($x) => (int)$x, $songIds), fn($x) => $x > 0));
            if ($playlistId <= 0 || count($songIds) === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'playlist_id and song_ids required']);
                exit;
            }

            $ownerCheck = $pdo->prepare('SELECT id FROM user_playlists WHERE id = :pid AND user_id = :uid LIMIT 1');
            $ownerCheck->bindValue(':pid', $playlistId, PDO::PARAM_INT);
            $ownerCheck->bindValue(':uid', $userId, PDO::PARAM_INT);
            $ownerCheck->execute();
            if (!$ownerCheck->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not own this playlist']);
                exit;
            }

            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO user_playlist_songs (playlist_id, song_id, added_at)
                 VALUES (:pid, :sid, NOW())"
            );
            foreach ($songIds as $sid) {
                $stmt->bindValue(':pid', $playlistId, PDO::PARAM_INT);
                $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
                $stmt->execute();
            }

            echo json_encode(['ok' => true]);
            exit;
        }

        if ($action === 'remove') {
            $playlistId = isset($_POST['playlist_id']) ? (int)$_POST['playlist_id'] : 0;
            $songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : 0;
            if ($playlistId <= 0 || $songId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'playlist_id and song_id required']);
                exit;
            }

            $ownerCheck = $pdo->prepare('SELECT id FROM user_playlists WHERE id = :pid AND user_id = :uid LIMIT 1');
            $ownerCheck->bindValue(':pid', $playlistId, PDO::PARAM_INT);
            $ownerCheck->bindValue(':uid', $userId, PDO::PARAM_INT);
            $ownerCheck->execute();
            if (!$ownerCheck->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not own this playlist']);
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM user_playlist_songs WHERE playlist_id = :pid AND song_id = :sid');
            $stmt->bindValue(':pid', $playlistId, PDO::PARAM_INT);
            $stmt->bindValue(':sid', $songId, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(['ok' => true]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
