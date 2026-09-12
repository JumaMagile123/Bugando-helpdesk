<?php
/**
 * ==========================================================
 * DATABASE CONNECTION CONFIG
 * Bugando Medical Centre - ICT HelpDesk System
 * ==========================================================
 * PDO provides prepared statements and helps prevent SQL injection.
 */

// Update these values for the target server.
define('DB_HOST', 'localhost');
define('DB_NAME', 'bugando_helpdesk');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Create the PDO connection.
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );

    // Throw database errors as exceptions for easier troubleshooting.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Stop the application when the database is unavailable.
    die("Database connection failed: " . $e->getMessage());
}
