<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header('Location: ../index.php');
    exit();
}

// Check if password has already been changed
$stmt = $pdo->prepare("SELECT password_changed FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['password_changed']) {
    header('Location: dashboard.php');
    exit();
}

// Check election status
$stmt = $pdo->prepare("SELECT status FROM election_status WHERE id = 1");
$stmt->execute();
$election = $stmt->fetch();

if ($election['status'] !== 'Pre-Voting') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, password_changed = 1 WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $success = 'Password changed successfully. Redirecting to dashboard...';
            header("Refresh: 2; URL=dashboard.php");
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - First Time Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }
        .change-password-container {
            width: 100%;
            max-width: 500px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin: auto;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e1e1e1;
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="change-password-container">
            <h2 class="text-center mb-4">Change Password</h2>
            <p class="text-center text-muted mb-4">Please change your password before proceeding to the dashboard.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <div class="form-text">Password must be at least 8 characters long.</div>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key me-2"></i>Change Password
                </button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
