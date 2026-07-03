<?php
// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = '127.0.0.1';
$db   = 'music_app_v2';

$user = 'root';  // Change to your MySQL user
$pass = '';      // Change to your MySQL password

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Debug: Log successful connection
error_log("Database connected successfully");

// Application secret used for token generation (change to a secure random value in production)
if (!defined('APP_SECRET')) {
    define('APP_SECRET', 'a3f5c9e1b7d24f6a9c8e3b1d5f6a7c9e');
}
