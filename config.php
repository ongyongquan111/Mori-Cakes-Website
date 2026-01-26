<?php
// Mori Cakes - Database Configuration (Phase-safe, Plaintext Auth)
// Phase 1: Local MySQL on EC2 (localhost/root)
// Phase 2+: Amazon RDS via environment variables

$host     = getenv('DB_HOST') ?: 'localhost';
$dbname   = getenv('DB_NAME') ?: 'mori_cakes';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

$pdo = null;

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    $pdo = null;
}
