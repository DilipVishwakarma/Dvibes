<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

$songs = getAllSongs($pdo, $limit, $offset);
echo json_encode($songs);
