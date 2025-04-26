<?php
// $host = 'evote.space';
// $dbname = 'u856246271_e_voting';
// $username = 'u856246271_e_voting';
// $password = 'Trichiliocosm07';



// Check if we are on localhost or live hosting
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
    // Localhost settings
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'root');       // Default XAMPP/MAMP username
    define('DB_PASSWORD', '');           // Default empty password for localhost
    define('DB_NAME', 'e_voting');       // Your local database name
} else {
    // Hostinger live server settings
    define('DB_SERVER', 'srv1153.hstgr.io');  // Your Hostinger database host
    define('DB_USERNAME', 'u856246271_e_voting');  // Your Hostinger database username
    define('DB_PASSWORD', 'Trichiliocosm07');  // Your Hostinger database password
    define('DB_NAME', 'u856246271_e_voting');  // Your Hostinger database name
}

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Enable error logging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful");
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}
?>
