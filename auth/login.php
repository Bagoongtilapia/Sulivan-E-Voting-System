<?php
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
        // Check if first_login exists in the user record
        $isFirstLogin = isset($user['first_login']) ? $user['first_login'] : 1;

        // For admins, bypass OTP
        if ($user['role'] === 'Super Admin' || $user['role'] === 'Sub-Admin') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: ../admin/dashboard.php');
            exit();
        }

        // For students, check if it's first login
        if ($isFirstLogin == 1) {
            // Update first_login status
            $stmt = $pdo->prepare("UPDATE users SET first_login = 0 WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Direct login without OTP for first time
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            header('Location: ../student/dashboard.php');
            exit();
        } else {
            // Not first login - generate and send OTP
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Save OTP to database
            $stmt = $pdo->prepare("INSERT INTO otp_codes (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $email, $otp, $expires_at]);

            // Send OTP via email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'aryiendzi.fernando08@gmail.com';
                $mail->Password = 'kqse gdwf nxmk qlgk';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('aryiendzi.fernando08@gmail.com', 'E-Voting System');
                $mail->addAddress($email, $user['name']);
                $mail->isHTML(true);
                $mail->Subject = 'Your Login Verification Code';
                $mail->Body = "
                    <h2>Your Verification Code</h2>
                    <p>Here is your verification code to complete login: <strong>{$otp}</strong></p>
                    <p>This code will expire in 5 minutes.</p>
                    <p>If you didn't request this code, please contact the administrator immediately.</p>
                ";

                $mail->send();

                // Set temporary session variables
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_user_email'] = $email;

                // Add this after line 46 in login.php (after generating OTP)
                error_log("Generated OTP for user {$user['id']}: {$otp}");

                header('Location: otp_verification.php');
                exit();
            } catch (Exception $e) {
                header('Location: ../index.php?error=Failed to send verification code. Please try again.');
                exit();
            }
        }
    } else {
        header('Location: ../index.php?error=Invalid email or password');
        exit();
    }
}

header('Location: ../index.php');
exit();
?>
