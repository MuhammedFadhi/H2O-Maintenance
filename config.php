<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'H2O_warranty');

// Connect to Database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Calculate all three maintenance/coverage expiry timestamps
 */
function calculateExpiries($regTimestamp = null)
{
    $start = $regTimestamp ?: time();
    return [
        'filter'   => date('Y-m-d H:i:s', $start + (90 * 86400)),
        'service'  => date('Y-m-d H:i:s', $start + (365 * 86400)),
        'warranty' => date('Y-m-d H:i:s', $start + (730 * 86400))
    ];
}
?>