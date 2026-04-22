<?php
// ==========================================
// Database Configuration for Live Web Server
// ==========================================

$host = 'localhost';      // Live server usually also uses localhost
$dbname = 'codexbiz_koo'; // Your server database name
$username = 'codexbiz_koo'; // Your server username
$password = 'g39Aal@PluwU'; // Your server password

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create database connection
try {
    // Method 2: Using PDO (recommended)
    // Note: Live environments usually do not need to specify port=3307, default port is used
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Set timezone in MySQL
    $pdo->exec("SET time_zone = '+08:00'");
    
} catch (PDOException $e) {
    // In production, it is recommended to hide detailed error messages to prevent path exposure
    die("Connection failed. Please check your database credentials in config-alt.php.");
    
    // If you need to see detailed errors during debugging, use the line below instead:
    // die("Connection failed: " . $e->getMessage());
}

// Set PHP timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Return connection for use in other files
return $pdo;
?>
