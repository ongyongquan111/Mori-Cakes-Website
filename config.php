<?php
/**
 * Mori Cakes - Database Configuration
 *
 * You only need to edit THIS file when moving between phases.
 *
 * ------------------------------------------------------------
 * PHASE 1 (LOCAL DATABASE ON EC2)  ✅ ACTIVE
 * ------------------------------------------------------------
 * Uses MariaDB/MySQL installed on the same EC2 instance.
 * Commands you run in Phase 1 create:
 *   Database: mori_cakes
 *   Tables:   imported from /var/www/html/database_schema.sql
 */
$host     = 'localhost';
$dbname   = 'mori_cakes';
$username = 'root';
$password = '';   // Amazon Linux local MariaDB often has empty root password in labs

/*
 * ------------------------------------------------------------
 * PHASE 2–4 (AMAZON RDS)  🟡 COMMENTED OUT FOR LATER
 * ------------------------------------------------------------
 * When you move to RDS, comment the Phase 1 block above and
 * uncomment + fill in this block.
 *
 * Example:
 * $host     = 'mori-cakes-db.xxxxxxxxxxxx.us-east-1.rds.amazonaws.com';
 * $dbname   = 'mori_cakes';
 * $username = 'admin';
 * $password = 'YOUR_RDS_MASTER_PASSWORD';
 */
// $host     = 'YOUR_RDS_ENDPOINT_HERE';
// $dbname   = 'mori_cakes';
// $username = 'admin';
// $password = 'YOUR_RDS_MASTER_PASSWORD';

$pdo = null;

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);

    // Sensible defaults (does NOT encrypt your data; it only controls error handling & fetch behavior)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Coursework-friendly: keep site running even if DB is down, but log the cause.
    error_log("Database connection error: " . $e->getMessage());
    $pdo = null;
}
