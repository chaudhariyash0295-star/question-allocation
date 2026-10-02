<?php
/**
 * Database Configuration & Connection
 * SMART QUESTION ALLOCATION SYSTEM
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_question_allocation');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', '/smart-question-allocation-system/');
define('APP_NAME', 'Smart Question Allocation System');
define('APP_VERSION', '1.0');

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("<div style='font-family:sans-serif;padding:30px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;border-radius:8px;max-width:600px;margin:50px auto;'>
                <h3 style='margin-top:0;'>Database Connection Error</h3>
                <p>Could not connect to MySQL server. Please ensure XAMPP MySQL service is running and the database <code>smart_question_allocation</code> has been imported.</p>
                <small>Error: " . htmlspecialchars($e->getMessage()) . "</small>
            </div>");
        }
    }
    return $pdo;
}

// Global PDO instance
$pdo = getDBConnection();
