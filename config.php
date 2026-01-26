<?php
/**
 * Mori Cakes - Database Configuration
 *
 * Phase 1 (Local DB on EC2):
 *   DB_HOST=localhost
 *   DB_NAME=mori_cakes
 *   DB_USER=root
 *   DB_PASS=   (empty)
 *
 * Phase 2+ (RDS):
 *   Set environment variables (recommended):
 *     DB_HOST, DB_NAME, DB_USER, DB_PASS
 *
 * Notes:
 * - Keeps coursework-friendly behavior: if DB fails, $pdo becomes null.
 * - No password hashing/encryption is introduced here (uses your existing schema/data).
 */

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
