<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Function to generate random password
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Check if user is logged in and is a Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    header('Location: ../index.php');
    exit();
}

// Handle bulk delete request first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    if (!isset($_POST['admin_ids'])) {
        $_SESSION['admin_message'] = ['type' => 'danger', 'text' => 'No admins selected for deletion'];
        header('Location: manage_admins.php');
        exit();
    }

    try {
        $adminIds = json_decode($_POST['admin_ids']);
        
        if (empty($adminIds)) {
            $_SESSION['admin_message'] = ['type' => 'danger', 'text' => 'No valid admin IDs provided'];
            header('Location: manage_admins.php');
            exit();
        }

        // Start transaction
        $pdo->beginTransaction();

        // Delete from otp_codes first
        $placeholders = str_repeat('?,', count($adminIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id IN ($placeholders)");
        $stmt->execute($adminIds);

        // Delete the admins
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'Sub-Admin'");
        if (!$stmt->execute($adminIds)) {
            throw new PDOException("Failed to delete admins");
        }

        // Commit the transaction
        $pdo->commit();
        header('Location: manage_admins.php?success=Sub-admin successfully deleted from the system');
        $_SESSION['admin_message'] = ['type' => 'success', 'text' => count($adminIds) . ' sub-admin(s) successfully deleted'];
        header('Location: manage_admins.php');
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in bulk admin deletion process: " . $e->getMessage());
        $_SESSION['admin_message'] = ['type' => 'danger', 'text' => 'System Error: Failed to delete sub-admins.'];
        header('Location: manage_admins.php');
        exit();
    }
}

// Handle single admin delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $admin_id = $_POST['admin_id'] ?? null;
    
    if (!$admin_id) {
        $_SESSION['admin_message'] = ['type' => 'danger', 'text' => 'No admin ID provided'];
        header('Location: manage_admins.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Delete from otp_codes first
        $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?");
        $stmt->execute([$admin_id]);

        // Delete the admin
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Sub-Admin'");
        if (!$stmt->execute([$admin_id])) {
            throw new PDOException("Failed to delete admin");
        }

        $pdo->commit();
        $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'Admin successfully deleted'];
        header('Location: manage_admins.php');
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['admin_message'] = ['type' => 'danger', 'text' => 'Failed to delete sub-admin'];
        header('Location: manage_admins.php');
        exit();
    }
}

// Handle POST request (Add/Edit admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    if (empty($name) || empty($email)) {
        header('Location: manage_admins.php?error=Name and email are required');
        exit();
    }

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            header('Location: manage_admins.php?error=Email already exists');
            exit();
        }

        // Generate random password
        $tempPassword = generatePassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

        // Create admin account
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, first_login, password_changed) VALUES (?, ?, ?, 'Sub-Admin', 1, 0)");
        $stmt->execute([$name, $email, $hashedPassword]);

        // Send password email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'sulivannationalhighschool@gmail.com';
            $mail->Password = 'nqhb kdea brfc xwvw';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('sulivannationalhighschool@gmail.com', 'E-VOTE');
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'Your Admin Account Password';
            $mail->Body = "
                <h2>Welcome to E-VOTE Admin Panel</h2>
                <p>Hello {$name},</p>
                <p>Your admin account has been created successfully.</p>
                <p>Here are your login credentials:</p>
                <p>Email: {$email}</p>
                <p>Temporary Password:</p>
                <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                    <h1 style='color: #393CB2; margin: 0; letter-spacing: 5px;'>{$tempPassword}</h1>
                </div>
                <p>Please log in and change your password immediately.</p>
                
            ";

            $mail->send();
            header('Location: manage_admins.php?success=Admin account created successfully. Login credentials have been sent via email.');
            exit();
        } catch (Exception $e) {
            header('Location: manage_admins.php?error=Failed to send password email');
            exit();
        }
    } catch (PDOException $e) {
        header('Location: manage_admins.php?error=System error occurred');
        exit();
    }
}

header('Location: manage_admins.php');
exit();
?>
