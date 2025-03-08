<?php
session_start();
require_once '../config/database.php';

// Check if user is Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit();
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = isset($_POST['admin_id']) ? $_POST['admin_id'] : null;
    
    if (!$id) {
        header('Location: manage_admins.php?error=No admin ID provided');
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // First, check if the user exists and is a sub-admin
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'Sub-Admin'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new PDOException("Admin not found or is not a sub-admin");
        }

        // Prevent deleting yourself
        if ($id == $_SESSION['user_id']) {
            throw new PDOException("You cannot delete your own account");
        }

        // Delete the admin account
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Sub-Admin'");
        if (!$stmt->execute([$id])) {
            throw new PDOException("Failed to delete admin record");
        }

        // If we got here, everything worked
        $pdo->commit();
        header('Location: manage_admins.php?success=Admin successfully deleted from the system');
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in admin deletion process: " . $e->getMessage());
        header('Location: manage_admins.php?error=' . urlencode('System Error: ' . $e->getMessage()));
        exit();
    }
}

// Handle POST request (Add/Edit admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($name) || empty($email)) {
        header('Location: manage_admins.php?error=Name and email are required');
        exit();
    }

    try {
        if (isset($_POST['admin_id'])) {
            // Edit existing admin
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
            header('Location: manage_admins.php?success=Admin updated successfully');
        } else {
            // Add new admin
            if (empty($password)) {
                header('Location: manage_admins.php?error=Password is required for new admin');
                exit();
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'Sub-Admin', NOW())");
            $stmt->execute([$name, $email, $hashed_password]);
            header('Location: manage_admins.php?success=Admin added successfully');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            header('Location: manage_admins.php?error=Email already exists');
        } else {
            header('Location: manage_admins.php?error=Failed to process admin');
        }
    }
    exit();
}

header('Location: manage_admins.php');
exit();
?>
