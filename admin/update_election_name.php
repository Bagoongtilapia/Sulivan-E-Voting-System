<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

if (isset($_POST['election_name'])) {
    $election_name = trim($_POST['election_name']);
    try {
        // Update the most recent election status record with the new name
        $stmt = $pdo->prepare("
            UPDATE election_status 
            SET election_name = ? 
            WHERE id = (SELECT id FROM (SELECT id FROM election_status ORDER BY id DESC LIMIT 1) AS temp)
        ");
        $stmt->execute([$election_name]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No election name provided']);
}
