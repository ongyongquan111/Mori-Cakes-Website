<?php
/**
 * Mori Cakes - Phase 1 to Phase 4 compatible DB config (PDO)
 *
 * Phase 1 (Local DB on EC2):
 *   - Defaults to localhost / root / empty password
 *   - Uses unix_socket automatically when needed (common on MariaDB/AL2023)
 *
 * Phase 2+ (RDS):
 *   - Set environment variables:
 *       DB_HOST, DB_NAME, DB_USER, DB_PASS
 *
 * Notes:
 *   - This coursework project supports plaintext passwords (as stored in users.password).
 */

function mori_get_pdo() {
    $host     = getenv('DB_HOST') ?: 'localhost';
    $dbname   = getenv('DB_NAME') ?: 'mori_cakes';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    // Phase 1 special case: local MariaDB root often uses unix_socket auth.
    $isLocal = in_array($host, ['localhost', '127.0.0.1'], true);
    $isRootNoPass = ($username === 'root' && $password === '');

    $dsnCandidates = [];

    if ($isLocal && $isRootNoPass) {
        // Try common socket paths first
        $socketPaths = [
            '/var/lib/mysql/mysql.sock',           // Amazon Linux / MariaDB
            '/var/run/mysqld/mysqld.sock',         // Ubuntu/Debian
            '/tmp/mysql.sock',
        ];

        foreach ($socketPaths as $sock) {
            $dsnCandidates[] = "mysql:unix_socket={$sock};dbname={$dbname};charset=utf8mb4";
        }
    }

    // Normal TCP DSN (works for both local TCP and RDS)
    $dsnCandidates[] = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

    foreach ($dsnCandidates as $dsn) {
        try {
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Continue trying next DSN candidate
            error_log("DB connect attempt failed for DSN '{$dsn}': " . $e->getMessage());
        }
    }

    return null;
}
