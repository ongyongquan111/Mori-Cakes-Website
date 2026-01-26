<?php
// Mori Cakes - database configuration (Phase-safe)
// Phase 1: defaults to local MySQL on the same EC2 (localhost/root)
// Phase 2+: set environment variables to point to RDS:
//   DB_HOST, DB_NAME, DB_USER, DB_PASS

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'mori_cakes';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

$pdo = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Keep behavior similar to original project: don't hard-crash the whole site,
    // but log the error and leave $pdo as null.
    error_log("Database connection error: " . $e->getMessage());
    $pdo = null;
}
