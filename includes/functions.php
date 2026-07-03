<?php
// Note: this file is shared by both pages and JSON APIs.
// Do not display errors to the client, otherwise it can corrupt JSON responses.
require_once __DIR__ . '/../config/database.php';
ini_set('display_errors', 0);


function getAllSongs($pdo, $limit = 50, $offset = 0, ?string $shuffleSeed = null)
{
    $useSeed = ($shuffleSeed !== null && $shuffleSeed !== '');
    $baseFrom = "
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
            GROUP_CONCAT(DISTINCT ar.name ORDER BY ar.name SEPARATOR ', ') AS artist_names
        FROM songs s
        LEFT JOIN albums a ON a.id = s.album_id
        LEFT JOIN languages l ON l.id = s.language_id
        LEFT JOIN eras e ON e.id = s.era_id
        LEFT JOIN song_artists sa ON sa.song_id = s.id
        LEFT JOIN artists ar ON ar.id = sa.artist_id
        LEFT JOIN regions r ON r.id = ar.region_id
        GROUP BY s.id
    ";
    // ORDER BY RAND() + OFFSET makes each page a new random draw (overlaps). With a
    // non-empty shuffle seed, order is stable per seed so LIMIT/OFFSET pages are
    // disjoint and Prev returns the same batch as before.
    $orderSql = $useSeed
        ? ' ORDER BY MD5(CONCAT(:shuffle_seed, CAST(s.id AS CHAR))), s.id '
        : ' ORDER BY RAND() ';

    $stmt = $pdo->prepare($baseFrom . $orderSql . ' LIMIT :limit OFFSET :offset');

    if ($useSeed) {
        $stmt->bindValue(':shuffle_seed', $shuffleSeed, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map('mapSongRowToCard', $rows);
}

function mapSongRowToCard(array $row): array
{
    $thumb = $row['thumbnail_path'] ?? null;
    if (empty($thumb)) {
        $thumb = $row['cover_image'] ?? null;
    }

    // Ensure URL-like path for frontend
    $thumbnailUrl = !empty($thumb)
        ? toPublicAudioOrImageUrl($thumb)
        : 'assets/images/default-album.jpg';

    $audioUrl = !empty($row['file_path'])
        ? toPublicAudioOrImageUrl($row['file_path'])
        : '';

    return [
        'id' => (int)($row['id'] ?? $row['song_id']),
        'title' => $row['title'] ?? $row['song_title'],
        'slug' => $row['slug'] ?? $row['song_slug'] ?? null,
        'album_id' => isset($row['album_id']) ? (int)$row['album_id'] : null,
        'album' => $row['album'] ?? $row['album_title'] ?? null,
        'artists' => $row['artists'] ?? $row['artist'] ?? $row['artist_names'] ?? null,
        'language' => $row['language'] ?? $row['language_name'] ?? null,
        'language_code' => $row['language_code'] ?? $row['language_code'] ?? null,
        'region' => $row['region'] ?? $row['region_name'] ?? null,
        'era' => $row['era'] ?? $row['era_name'] ?? null,
        'duration' => isset($row['duration']) ? (int)$row['duration'] : null,
        'file_path' => $row['file_path'] ?? null,
        'thumbnail_url' => $thumbnailUrl,
        'audio_url' => $audioUrl,
    ];
}

function toPublicAudioOrImageUrl(?string $path): string
{
    if (empty($path)) return '';
    $path = str_replace('\\', '/', $path);
    // expected DB paths like "storage/music/xxx.mp3" or "music/xxx.mp3" or "storage/thumbnails/..."
    $path = ltrim($path, '/');

    if (str_starts_with($path, 'music/')) {
        return 'storage/music/' . substr($path, strlen('music/'));
    }
    if (str_starts_with($path, 'storage/music/')) {
        return 'storage/music/' . substr($path, strlen('storage/music/'));
    }
    if (str_starts_with($path, 'storage/thumbnails/')) {
        return 'storage/thumbnails/' . substr($path, strlen('storage/thumbnails/'));
    }

    // album cover_image might already be a public path
    return $path;
}


function searchSongs($pdo, $query, $limit = 50, $offset = 0)
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    // Simple tokenization for better LIKE matching
    $tokens = preg_split('/\s+/', $query);
    $tokens = array_values(array_filter($tokens));

    // Build an OR expression across song title + artist name
    // Uses LIKE for compatibility; assumes typical schema: song_artists(song_id, artist_id), artists(id,name)
    $likeParts = [];
    foreach ($tokens as $t) {
        $likeParts[] = '%' . $t . '%';
    }

    // If tokenization fails, fallback to full query
    if (!$likeParts) {
        $likeParts = ['%' . $query . '%'];
    }

    // LIMIT/OFFSET are numeric so bind as ints
    $sql = "
        SELECT DISTINCT
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
        WHERE (" . implode(' OR ', array_fill(0, count($likeParts), 's.title LIKE ?')) . ")
           OR (" . implode(' OR ', array_fill(0, count($likeParts), 'ar.name LIKE ?')) . ")
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($sql);

    $i = 1;
    foreach ($likeParts as $p) {
        $stmt->bindValue($i, $p, PDO::PARAM_STR);
        $i++;
    }
    foreach ($likeParts as $p) {
        $stmt->bindValue($i, $p, PDO::PARAM_STR);
        $i++;
    }

    $stmt->bindValue($i, (int)$limit, PDO::PARAM_INT);
    $i++;
    $stmt->bindValue($i, (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map('mapSongRowToCard', $rows);
}



function getSongsByGenre($pdo, $genreSlug, $limit = 50, $offset = 0)
{
    // This project’s DB schema appears to vary (missing s.genre_id).
    // Keep this query resilient: only filter if the column exists.
    // Fallback: return empty list when filtering can’t be done.
    $stmt = $pdo->prepare("
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
            GROUP_CONCAT(DISTINCT ar.name ORDER BY ar.name SEPARATOR ', ') AS artist_names
        FROM songs s
        LEFT JOIN albums a ON a.id = s.album_id
        LEFT JOIN languages l ON l.id = s.language_id
        LEFT JOIN eras e ON e.id = s.era_id
        LEFT JOIN song_artists sa ON sa.song_id = s.id
        LEFT JOIN artists ar ON ar.id = sa.artist_id
        LEFT JOIN regions r ON r.id = ar.region_id
        WHERE s.genre_slug = :genre OR s.genre = :genre
        GROUP BY s.id
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':genre', $genreSlug);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map('mapSongRowToCard', $rows);
}

function getSongsByArtistId($pdo, $artistId, $limit = 50, $offset = 0)
{
    // Typical schema assumed: song_artists(song_id, artist_id), songs(id, ...)
    $stmt = $pdo->prepare("
        SELECT DISTINCT
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
        WHERE sa.artist_id = :artistId1 OR ar.id = :artistId2
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':artistId1', $artistId, PDO::PARAM_INT);
    $stmt->bindValue(':artistId2', $artistId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map('mapSongRowToCard', $rows);
}

function getSongsByArtist($pdo, $artistName, $limit = 50, $offset = 0)
{
    // Filter by artist name using joins
    $stmt = $pdo->prepare("
        SELECT DISTINCT
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
        WHERE ar.name = :artistName
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':artistName', $artistName, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map('mapSongRowToCard', $rows);
}

function getGenres($pdo)
{
    $stmt = $pdo->query("SELECT * FROM genres ORDER BY name");
    return $stmt->fetchAll();
}

function getArtists($pdo, $limit = null)
{
    $sql = "SELECT * FROM artists ORDER BY name";
    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getSongById($pdo, $id)
{
    $stmt = $pdo->prepare("
        SELECT s.*, g.name AS genre_name 
        FROM songs s 
        JOIN genres g ON s.genre_id = g.id 
        WHERE s.id = :id
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch();
}

function formatDuration($seconds)
{
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf('%d:%02d', $minutes, $secs);
}

function getSongUrl($filePath)
{
    return "music/" . str_replace('music/', '', $filePath);
}
