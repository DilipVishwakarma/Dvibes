<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Support numeric id or signed token 's'
$songId = 0;
if (isset($_GET['id'])) {
    $songId = (int)$_GET['id'];
}
if ($songId <= 0 && isset($_GET['s'])) {
    $token = $_GET['s'];
    $base64 = strtr($token, '-_', '+/');
    $padding = strlen($base64) % 4;
    if ($padding > 0) {
        $base64 .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($base64, true);
    if ($decoded !== false) {
        // New compact format: 4-byte songId + 10-byte HMAC
        if (strlen($decoded) === 14) {
            $idPart = unpack('N', substr($decoded, 0, 4));
            $hmac = substr($decoded, 4);
            if ($idPart && isset($idPart[1])) {
                $idValue = (int)$idPart[1];
                $calc = substr(hash_hmac('sha256', (string)$idValue, APP_SECRET, true), 0, 10);
                if (hash_equals($calc, $hmac)) {
                    $songId = $idValue;
                }
            }
        } else {
            // Fallback to legacy format base64(id:hmac)
            $decodedLegacy = $decoded;
            if (strpos($decodedLegacy, ':') !== false) {
                list($idPart, $hmacLegacy) = explode(':', $decodedLegacy, 2);
                if (ctype_digit($idPart)) {
                    $calcLegacy = hash_hmac('sha256', (string)$idPart, APP_SECRET);
                    if (hash_equals($calcLegacy, $hmacLegacy)) {
                        $songId = (int)$idPart;
                    }
                }
            }
        }
    }
}

if ($songId <= 0) {
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
        WHERE s.id = :id
        GROUP BY s.id
        LIMIT 1"
    );
    $stmt->bindValue(':id', $songId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode([]);
        exit;
    }

    echo json_encode(mapSongRowToCard($row));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
