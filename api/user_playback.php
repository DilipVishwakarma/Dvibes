<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require login (guest counts as logged-in because auth_guest_login sets user_id)
if (auth_current_user_id() === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = auth_current_user_id();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare('SELECT song_id, last_position_seconds, updated_at FROM user_playback_state WHERE user_id = :uid LIMIT 1');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        echo json_encode($row ?: null);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : null;
        $pos = isset($_POST['last_position_seconds']) ? (int)$_POST['last_position_seconds'] : 0;

        // Upsert
        $stmt = $pdo->prepare('
            INSERT INTO user_playback_state (user_id, song_id, last_position_seconds)
            VALUES (:uid, :song_id, :pos)
            ON DUPLICATE KEY UPDATE
                song_id = VALUES(song_id),
                last_position_seconds = VALUES(last_position_seconds)
        ');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        if ($songId) {
            $stmt->bindValue(':song_id', $songId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':song_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':pos', $pos, PDO::PARAM_INT);
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
