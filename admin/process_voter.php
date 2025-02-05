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
        header('Location: manage_voters.php?error=Unauthorized action');
        exit();
    }

    $id = $_GET['id'];
    try {
        // Check if voter has already voted
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE student_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            header('Location: manage_voters.php?error=Cannot delete voter who has already cast their vote');
            exit();
        }

        // Check if voter is a candidate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE student_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            header('Location: manage_voters.php?error=Cannot delete voter who is a candidate');
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Student'");
        $stmt->execute([$id]);
        header('Location: manage_voters.php?success=Voter deleted successfully');
    } catch (PDOException $e) {
        header('Location: manage_voters.php?error=Failed to delete voter');
    }
    exit();
}

// Handle POST request (Add/Edit voter)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($name) || empty($email)) {
        header('Location: manage_voters.php?error=Name and email are required');
        exit();
    }

    try {
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            // Edit existing voter
            $voter_id = $_POST['voter_id'];
            if (!empty($password)) {
                // Update with new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'Student'");
                $stmt->execute([$name, $email, $hashed_password, $voter_id]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'Student'");
                $stmt->execute([$name, $email, $voter_id]);
            }
            header('Location: manage_voters.php?success=Voter updated successfully');
        } else {
            // Add new voter
            if (empty($password)) {
                header('Location: manage_voters.php?error=Password is required for new voters');
                exit();
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'Student')");
            $stmt->execute([$name, $email, $hashed_password]);
            header('Location: manage_voters.php?success=Voter added successfully');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            header('Location: manage_voters.php?error=Email already exists');
        } else {
            header('Location: manage_voters.php?error=Failed to process voter');
        }
    }
    exit();
}

header('Location: manage_voters.php');
exit();
?>
