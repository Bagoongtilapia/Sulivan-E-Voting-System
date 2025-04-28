<?php
// Remove debug output
date_default_timezone_set('Asia/Manila'); // Adjust to your timezone
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set session variables first
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];

        // Check if password needs to be changed
        $stmt = $pdo->prepare("SELECT password_changed FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $passwordStatus = $stmt->fetch();

        // If password hasn't been changed, redirect to change password
        if (!$passwordStatus['password_changed']) {
            if ($user['role'] === 'Student') {
                header('Location: ../student/change_password.php');
                exit();
            } else if ($user['role'] === 'Super Admin' || $user['role'] === 'Sub-Admin') {
                header('Location: ../admin/change_password.php');
                exit();
            }
        }

        // If password is already changed, proceed with OTP
        $otp = sprintf("%06d", mt_rand(0, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Reset OTP attempts counter
        $_SESSION['otp_attempts'] = 0;

        // Save OTP to database
        $stmt = $pdo->prepare("INSERT INTO otp_codes (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], $email, $otp, $expires_at]);

        // Send OTP via email
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
            $mail->addAddress($email, $user['name']);
            $mail->isHTML(true);
            $mail->Subject = 'Your Login Verification Code';
            $mail->Body = "
                <h2>Your Verification Code</h2>
                <p>Here is your verification code to complete login:</p>
                <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                    <h1 style='color: #393CB2; margin: 0; letter-spacing: 5px;'>{$otp}</h1>
                </div>
                <p>This code will expire in 5 minutes.</p>
                <p>If you didn't request this code, please contact the administrator immediately.</p>
            ";

            $mail->send();

            // Set temporary session variables
            $_SESSION['temp_user_id'] = $user['id'];
            $_SESSION['temp_user_email'] = $email;

            error_log("Generated OTP for user {$user['id']}: {$otp}");

            header('Location: otp_verification.php');
            exit();
        } catch (Exception $e) {
            header('Location: ../index.php?error=Failed to send verification code. Please try again.');
            exit();
        }
    } else {
        $_SESSION['login_error'] = 'Invalid email or password';
        header('Location: ../index.php');
        exit();
    }
}

// If not a POST request, redirect with error
$_SESSION['login_error'] = 'Invalid request method';
header('Location: ../index.php');
exit();
