<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_POST['election_name'])) {
    $_SESSION['election_name'] = trim($_POST['election_name']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No election name provided']);
}
