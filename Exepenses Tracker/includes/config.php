<?php
// ==========================================
// Database Configuration for Live Web Server
// ==========================================

// 1. Host: Usually live servers also use "localhost", no need to add :3307 port
define('DB_HOST', 'localhost'); 

// 2. Username: Use the server username you provided
define('DB_USER', 'codexbiz_koo');

// 3. Password: Use the server password you provided
define('DB_PASS', 'g39Aal@PluwU');

// 4. Database Name: Use the server database name you provided
define('DB_NAME', 'codexbiz_koo');

// Start session (Keep this as is)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create database connection (PDO)
try {
    // Note: Port number is removed here, servers usually use default port
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    
    // Set error mode to exception for easier debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set charset to prevent Chinese/Unicode encoding issues (recommended)
    $pdo->exec("set names utf8mb4");

} catch(PDOException $e) {
    // In production, it is recommended not to display detailed errors
    // But kept for debugging convenience
    die("Connection failed: " . $e->getMessage());
}

// Set timezone (Keep this for Malaysia time)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Debug function (Keep for your convenience)
function debug($data) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}
?>
