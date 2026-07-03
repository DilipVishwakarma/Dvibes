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
        $stmt = $pdo->prepare('SELECT id, name, created_at FROM user_playlists WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'create';

        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                http_response_code(400);
                echo json_encode(['error' => 'name required']);
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO user_playlists (user_id, name) VALUES (:uid, :name)');
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->execute();
            $id = (int)$pdo->lastInsertId();
            echo json_encode(['ok' => true, 'playlist_id' => $id]);
            exit;
        }

        if ($action === 'delete') {
            $playlistId = isset($_POST['playlist_id']) ? (int)$_POST['playlist_id'] : 0;
            if ($playlistId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'playlist_id required']);
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM user_playlists WHERE id = :pid AND user_id = :uid');
            $stmt->bindValue(':pid', $playlistId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();

            echo json_encode(['ok' => true]);
            exit;
        }

        if ($action === 'rename') {
            $playlistId = isset($_POST['playlist_id']) ? (int)$_POST['playlist_id'] : 0;
            $name = trim((string)($_POST['name'] ?? ''));
            if ($playlistId <= 0 || $name === '') {
                http_response_code(400);
                echo json_encode(['error' => 'playlist_id and valid name required']);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE user_playlists SET name = :name WHERE id = :pid AND user_id = :uid');
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':pid', $playlistId, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
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
