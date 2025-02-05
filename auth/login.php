<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            // Redirect based on role
            switch ($user['role']) {
                case 'Super Admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'Sub-Admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'Student':
                    header('Location: ../student/dashboard.php');
                    break;
                default:
                    header('Location: ../index.php?error=Invalid role');
            }
            exit();
        } else {
            header('Location: ../index.php?error=Invalid email or password');
            exit();
        }
    } catch (PDOException $e) {
        header('Location: ../index.php?error=Database error');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}
?>
