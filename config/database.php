<?php
$host = 'localhost';
$dbname = 'e_voting';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Enable error logging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful");
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}
?>
