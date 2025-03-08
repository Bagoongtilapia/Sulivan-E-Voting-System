<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a Sub-Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Sub-Admin') {
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
    <title>Change Password - E-VOTE!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #393CB2;
            --primary-light: #5558CD;
            --primary-dark: #2A2D8F;
            --accent-color: #E8E9FF;
            --gradient-primary: linear-gradient(135deg, #393CB2, #5558CD);
            --light-bg: #F8F9FF;
        }

        body {
            background: linear-gradient(135deg, #E8E9FF 0%, #F8F9FF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
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
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(57, 60, 178, 0.1);
            margin: auto;
        }
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
            margin-bottom: 28px;
        }
        .form-control {
            position: relative;
            border-radius: 10px;
            padding: 14px 15px;
            border: 2px solid var(--accent-color);
            background-image: none !important;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 1rem;
            z-index: 1;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(57, 60, 178, 0.1);
        }
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s ease;
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2A2D8F, #393CB2);
            transform: translateY(-2px);
        }
        .header {
            background: var(--gradient-primary);
            color: white;
            padding: 25px 20px;
            text-align: center;
            margin: -40px -40px 40px -40px;
            border-radius: 15px 15px 0 0;
        }
        .header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 1rem;
        }
        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 12px;
            display: block;
            font-size: 1.1rem;
        }
        .form-text {
            color: #666;
            font-size: 0.9rem;
            margin-top: 8px;
            margin-bottom: 24px;
            margin-left: 2px;
        }
        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .alert i {
            margin-right: 10px;
            font-size: 1.1rem;
        }
        .alert-danger {
            background-color: #ffe8e8;
            border-color: #ffcfcf;
            color: #d63939;
        }
        .alert-success {
            background-color: #e8fff3;
            border-color: #cfffea;
            color: #39d686;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="change-password-container">
            <div class="header">
                <h2>Change Password</h2>
                <p>Please change your password before continuing</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-4">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <div class="form-text">Password must be at least 8 characters long</div>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>
