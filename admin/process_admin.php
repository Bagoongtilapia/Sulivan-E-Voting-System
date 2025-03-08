<?php
session_start();
require_once '../config/database.php';

// Check if user is Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit();
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    try {
        // Prevent deleting yourself
        if ($id == $_SESSION['user_id']) {
            header('Location: manage_admins.php?error=You cannot delete your own account');
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Sub-Admin'");
        $stmt->execute([$id]);
        header('Location: manage_admins.php?success=Sub-Admin removed successfully');
    } catch (PDOException $e) {
        header('Location: manage_admins.php?error=Failed to remove Sub-Admin');
    }
    exit();
}

// Handle POST request (Add/Edit Sub-Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($name) || empty($email)) {
        header('Location: manage_admins.php?error=Name and email are required');
        exit();
    }

    try {
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            // Edit existing Sub-Admin
            $admin_id = $_POST['admin_id'];
            
            // Prevent editing yourself
            if ($admin_id == $_SESSION['user_id']) {
                header('Location: manage_admins.php?error=You cannot edit your own account from here');
                exit();
            }

            if (!empty($password)) {
                // Update with new password and reset password_changed
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, password_changed = 0 WHERE id = ? AND role = 'Sub-Admin'");
                $stmt->execute([$name, $email, $hashed_password, $admin_id]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'Sub-Admin'");
                $stmt->execute([$name, $email, $admin_id]);
            }
            header('Location: manage_admins.php?success=Sub-Admin updated successfully');
        } else {
            // Add new Sub-Admin
            if (empty($password)) {
                header('Location: manage_admins.php?error=Password is required for new Sub-Admin');
                exit();
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, password_changed) VALUES (?, ?, ?, 'Sub-Admin', 0)");
            $stmt->execute([$name, $email, $hashed_password]);
            header('Location: manage_admins.php?success=Sub-Admin added successfully');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            header('Location: manage_admins.php?error=Email already exists');
        } else {
            header('Location: manage_admins.php?error=Failed to process Sub-Admin');
        }
    }
    exit();
}

header('Location: manage_admins.php');
exit();
?>
