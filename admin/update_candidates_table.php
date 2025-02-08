<?php
require_once '../config/database.php';

try {
    // Add image column to candidates table
    $pdo->exec("ALTER TABLE candidates ADD COLUMN image_url VARCHAR(255) DEFAULT NULL");
    echo "Successfully added image_url column to candidates table";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {  // Duplicate column error
        echo "Column image_url already exists";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
