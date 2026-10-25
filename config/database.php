<?php
/**
 * Database Configuration & Connection
 * Meat Shop Management System
 */

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'meatshop_db');

// Detect available port (3306 or 3307)
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $ports = [3306, 3307];
    $last_error = '';

    foreach ($ports as $port) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            $last_error = $e->getMessage();
        }
    }

    die("Database Connection Error: " . $last_error);
}

$pdo = get_db_connection();
