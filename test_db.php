<?php
require_once 'config/database.php';

try {
    // Test users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    echo "Users table structure:\n";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
    // Test votes table
    $stmt = $pdo->query("SHOW COLUMNS FROM votes");
    echo "\nVotes table structure:\n";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
