<?php
date_default_timezone_set('Asia/Manila'); // Adjust to your timezone
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_user_email'])) {
    header('Location: ../index.php');
    exit();
}

// Initialize attempt counter if not set
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'];
    $user_id = $_SESSION['temp_user_id'];
    $email = $_SESSION['temp_user_email'];

    // Check if maximum attempts reached
    if ($_SESSION['otp_attempts'] >= 3) {
        header('Location: otp_verification.php?error=Maximum attempts reached. Please request a new OTP.&show_resend=true');
        exit();
    }

    // Debug logging
    error_log("Verifying OTP: {$otp} for user {$user_id} with email {$email}");

    // Verify OTP
    $stmt = $pdo->prepare("SELECT * FROM otp_codes 
                          WHERE user_id = ? 
                          AND email = ? 
                          AND otp_code = ? 
                          AND is_used = 0 
                          AND expires_at > NOW()
                          ORDER BY created_at DESC 
                          LIMIT 1");
    $stmt->execute([$user_id, $email, $otp]);
    $result = $stmt->fetch();

    if ($result) {
        // Reset attempts on successful verification
        $_SESSION['otp_attempts'] = 0;

        // Mark OTP as used
        $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
        $stmt->execute([$result['id']]);

        // Set full session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $email;
        
        // Get user info from users table
        $stmt = $pdo->prepare("SELECT role, name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch();
        $_SESSION['user_role'] = $user_info['role'];
        $_SESSION['user_name'] = $user_info['name'];

        // Clear temporary session variables
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_user_email']);

        // Redirect based on role
        if ($_SESSION['user_role'] === 'Student') {
            header('Location: ../student/dashboard.php');
        } else {
            header('Location: ../admin/dashboard.php');
        }
        exit();
    } else {
        // Increment attempt counter
        $_SESSION['otp_attempts']++;
        $remaining_attempts = 3 - $_SESSION['otp_attempts'];

        // Check why verification failed for debugging
        $stmt = $pdo->prepare("SELECT 
            otp_code,
            is_used,
            expires_at
        FROM otp_codes 
        WHERE user_id = ? 
        AND email = ?
        ORDER BY created_at DESC 
        LIMIT 1");
        $stmt->execute([$user_id, $email]);
        $debug_result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Determine status in PHP
        $status = 'valid';
        if ($debug_result) {
            $current_time = time();
            $expiry_time = strtotime($debug_result['expires_at']);
            
            if ($expiry_time < $current_time) {
                $status = 'expired';
            } elseif ($debug_result['is_used'] == 1) {
                $status = 'used';
            }
        }
        
        error_log("Debug OTP check: " . print_r($debug_result, true) . " Status: " . $status);

        if ($_SESSION['otp_attempts'] >= 3) {
            header('Location: otp_verification.php?error=Maximum attempts reached. Please request a new OTP.&show_resend=true');
        } else {
            header('Location: otp_verification.php?error=Invalid OTP. ' . $remaining_attempts . ' attempts remaining.');
        }
        exit();
    }
}
?>
