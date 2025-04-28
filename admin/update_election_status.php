<?php
session_start();
require_once '../config/database.php';

// Check if user is Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $status = $_POST['status'];
    $election_name = trim($_POST['election_name'] ?? '');
    $validStatuses = ['Pre-Voting', 'Voting', 'Ended'];

    if (in_array($status, $validStatuses)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO election_status (status, election_name) VALUES (?, ?)");
            $stmt->execute([$status, $election_name]);
            header('Location: dashboard.php?success=Status updated successfully');
        } catch (PDOException $e) {
            header('Location: dashboard.php?error=Failed to update status');
        }
    } else {
        header('Location: dashboard.php?error=Invalid status');
    }
} else {
    header('Location: dashboard.php');
}
exit();
?>
