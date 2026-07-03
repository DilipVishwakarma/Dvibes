<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (auth_current_user_id() === null) {
    // Not authenticated => no guest session yet
    echo json_encode(['remaining' => null]);
    exit;
}

$userId = auth_current_user_id();

// Check if current user is marked guest
$stmtUser = $pdo->prepare('SELECT is_guest FROM users WHERE id = :id LIMIT 1');
$stmtUser->bindValue(':id', $userId, PDO::PARAM_INT);
$stmtUser->execute();
$user = $stmtUser->fetch();
if (!$user) {
    echo json_encode(['remaining' => null]);
    exit;
}

if (empty($user['is_guest'])) {
    // Not a guest user; unlimited
    echo json_encode(['remaining' => -1]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM user_listening_history WHERE user_id = :uid AND played_at >= CURDATE()');
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    $count = (int)($row['cnt'] ?? 0);
    $limit = 10;
    $remaining = max(0, $limit - $count);
    echo json_encode(['remaining' => $remaining, 'limit' => $limit, 'played_today' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
