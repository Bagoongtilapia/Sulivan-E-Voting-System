<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT u.*, e.status as election_status FROM users u 
                              CROSS JOIN election_status e 
                              WHERE u.email = ? AND e.id = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            // Redirect based on role and password change status
            switch ($user['role']) {
                case 'Super Admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'Sub-Admin':
                    // If password hasn't been changed and it's pre-voting, redirect to change password
                    if (!$user['password_changed'] && $user['election_status'] === 'Pre-Voting') {
                        header('Location: ../admin/change_password.php');
                    } else {
                        header('Location: ../admin/dashboard.php');
                    }
                    break;
                case 'Student':
                    // If password hasn't been changed and it's pre-voting, redirect to change password
                    if (!$user['password_changed'] && $user['election_status'] === 'Pre-Voting') {
                        header('Location: ../student/change_password.php');
                    } else {
                        header('Location: ../student/dashboard.php');
                    }
                    break;
                default:
                    header('Location: ../index.php');
            }
            exit();
        } else {
            // Just reload the login page on error
            header('Location: ../index.php');
            exit();
        }
    } catch (PDOException $e) {
        // Just reload the login page on database error
        header('Location: ../index.php');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}
?>
