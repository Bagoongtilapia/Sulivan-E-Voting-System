<?php
session_start();
require_once '../config/database.php';
require_once '../phpmailer/Exception.php';
require_once '../phpmailer/PHPMailer.php';
require_once '../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_user_email'])) {
    header('Location: ../index.php');
    exit();
}

try {
    // Generate new 6-digit OTP
    $new_otp = sprintf('%06d', mt_rand(0, 999999));
    $user_id = $_SESSION['temp_user_id'];
    $email = $_SESSION['temp_user_email'];
    
    // Mark all previous OTPs as used
    $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE user_id = ? AND email = ?");
    $stmt->execute([$user_id, $email]);
    
    // Insert new OTP
    $stmt = $pdo->prepare("INSERT INTO otp_codes (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
    $stmt->execute([$user_id, $email, $new_otp]);

    // Reset attempt counter
    $_SESSION['otp_attempts'] = 0;

    // Send email with new OTP
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'sulivannationalhighschool@gmail.com'; // Replace with your email
    $mail->Password = 'nqhb kdea brfc xwvw'; // Replace with your app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Recipients
    $mail->setFrom('sulivannationalhighschool@gmail.com', 'E-Voting System'); // Replace with your email and name
    $mail->addAddress($email);

    // Get user's name from database
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $user_name = $user['name'] ?? 'User';

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'New OTP Code - E-Voting System';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #393CB2;'>Hello {$user_name},</h2>
            <p>Your new OTP code for the E-Voting System is:</p>
            <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                <h1 style='color: #393CB2; margin: 0; letter-spacing: 5px;'>{$new_otp}</h1>
            </div>
            <p>This code will expire in 5 minutes.</p>
            <p style='color: #666; font-size: 0.9em;'>If you didn't request this code, please ignore this email.</p>
        </div>
    ";

    $mail->send();
    
    // Redirect back with success message
    header('Location: otp_verification.php?message=New OTP has been sent to your email.');
    exit();

} catch (Exception $e) {
    error_log("Error sending OTP: " . $e->getMessage());
    header('Location: otp_verification.php?error=Failed to send new OTP. Please try again.');
    exit();
}
?> 