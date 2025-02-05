<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    if ($_SESSION['user_role'] !== 'Super Admin') {
        header('Location: manage_positions.php?error=Unauthorized action');
        exit();
    }

    $id = $_GET['id'];
    try {
        // Check if position has candidates
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE position_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            header('Location: manage_positions.php?error=Cannot delete position with candidates');
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM positions WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: manage_positions.php?success=Position deleted successfully');
    } catch (PDOException $e) {
        header('Location: manage_positions.php?error=Failed to delete position');
    }
    exit();
}

// Handle POST request (Add/Edit position)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $position_name = trim($_POST['position_name']);

    if (empty($position_name)) {
        header('Location: manage_positions.php?error=Position name cannot be empty');
        exit();
    }

    try {
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            // Edit existing position
            $position_id = $_POST['position_id'];
            $stmt = $pdo->prepare("UPDATE positions SET position_name = ? WHERE id = ?");
            $stmt->execute([$position_name, $position_id]);
            header('Location: manage_positions.php?success=Position updated successfully');
        } else {
            // Add new position
            $stmt = $pdo->prepare("INSERT INTO positions (position_name) VALUES (?)");
            $stmt->execute([$position_name]);
            header('Location: manage_positions.php?success=Position added successfully');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            header('Location: manage_positions.php?error=Position name already exists');
        } else {
            header('Location: manage_positions.php?error=Failed to process position');
        }
    }
    exit();
}

header('Location: manage_positions.php');
exit();
?>
