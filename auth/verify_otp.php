<?php
date_default_timezone_set('Asia/Manila'); // Adjust to your timezone
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_user_email'])) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'];
    $user_id = $_SESSION['temp_user_id'];
    $email = $_SESSION['temp_user_email'];

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

    // Debug logging
    if (!$result) {
        // Check why verification failed
        $stmt = $pdo->prepare("SELECT 
            otp_code,
            is_used,
            expires_at,
            NOW() as current_time
            FROM otp_codes 
            WHERE user_id = ? 
            AND email = ?
            ORDER BY created_at DESC 
            LIMIT 1");
        $stmt->execute([$user_id, $email]);
        $debug_result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Debug OTP check: " . print_r($debug_result, true));
    }

    if ($result) {
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
        header('Location: otp_verification.php?error=Invalid or expired OTP');
        exit();
    }
}
?>
