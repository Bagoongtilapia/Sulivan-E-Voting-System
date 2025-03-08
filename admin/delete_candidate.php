<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $candidate_id = $_POST['candidate_id'];

    // Delete the candidate
    $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
