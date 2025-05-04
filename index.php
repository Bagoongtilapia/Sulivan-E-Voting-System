<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Voting System - Login</title>
    <link rel="icon" type="image/x-icon" href="/Sulivan-E-Voting-System/image/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #393CB2;
            --primary-light: #5558CD;
            --primary-dark: #2A2D8F;
            --accent-color: #E8E9FF;
            --gradient-primary: linear-gradient(135deg, #393CB2, #5558CD);
        }

        body {
            min-height: 100vh;
            background: linear-gradient(120deg, #E8E9FF 0%, #F8F9FF 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            position: relative;
        }
        .background-gradient {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 0;
            background: radial-gradient(circle at 70% 30%, #393CB2 0%, #5558CD 40%, #E8E9FF 100%);
            opacity: 0.18;
        }
        .container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1;
        }
        .login-container {
            background: rgba(255,255,255,0.85);
            border-radius: 22px;
            box-shadow: 0 8px 32px rgba(57, 60, 178, 0.18);
            backdrop-filter: blur(8px);
            overflow: hidden;
            width: 100%;
            max-width: 410px;
            padding: 44px 32px 32px 32px;
            animation: fadeInUp 0.8s cubic-bezier(.39,.575,.565,1) both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            background: var(--gradient-primary);
            color: white;
            padding: 18px 10px 16px 10px;
            text-align: center;
            margin-bottom: 28px;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(57, 60, 178, 0.10);
        }
        .login-logo {
            width: 60px; height: 60px;
            margin-bottom: 10px;
            border-radius: 50%;
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(57, 60, 178, 0.10);
            margin-left: auto; margin-right: auto;
            overflow: hidden;
        }
        .login-logo img {
            width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
        }
        .system-name {
            font-size: 2rem;
            margin-bottom: 5px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .system-tagline {
            opacity: 0.93;
            font-size: 1rem;
            margin-bottom: 0;
            font-weight: 400;
        }
        .form-label {
            font-weight: 500;
            color: var(--primary-dark);
            margin-bottom: 6px;
        }
        .form-floating {
            margin-bottom: 1.5rem;
        }
        .form-control {
            padding: 1.25rem 4.5rem 1.25rem 1rem;
            max-width: 100%;
            font-size: 1.05rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border-radius: 12px;
            border: 2px solid #e1e1e1;
            background: rgba(255,255,255,0.95) !important;
            box-shadow: none;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.18rem rgba(57, 60, 178, 0.13);
        }
        .form-floating > .input-icon,
        .form-floating > .toggle-password {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            color: #5558CD;
            font-size: 1.25rem;
            opacity: 0.7;
            transition: opacity 0.2s, background 0.2s, color 0.2s;
            background: #f8f9ff;
            border-radius: 50%;
            height: 2rem;
            width: 2rem;
            max-width: 2rem;
            min-width: 2rem;
            box-shadow: 0 1px 4px rgba(57,60,178,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
        }
        .form-floating > .input-icon {
            pointer-events: none;
            font-size: 1.15rem;
        }
        .form-floating > .toggle-password {
            cursor: pointer;
            pointer-events: auto;
            font-size: 1.25rem;
            max-width: 1.5em;
            overflow: hidden;
        }
        .form-floating > .toggle-password:hover {
            background: #f0f1ff;
            opacity: 1;
        }
        .form-floating > .toggle-password i {
            font-family: 'Font Awesome 6 Free', 'FontAwesome', Arial, sans-serif !important;
            font-weight: 900 !important;
            display: inline-block;
            max-width: 1.5em;
            overflow: hidden;
        }
        .form-floating > .toggle-password i ~ i {
            display: none !important;
        }
        .form-floating > label {
            z-index: 4;
            left: 1rem;
        }
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 14px 0;
            font-weight: 700;
            font-size: 1.1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(57, 60, 178, 0.10);
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
        }
        .btn-primary:hover, .btn-primary:focus {
            background: linear-gradient(135deg, #2A2D8F, #393CB2);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 4px 16px rgba(57, 60, 178, 0.16);
        }
        @media (max-width: 600px) {
            .login-container {
                padding: 22px 6px 18px 6px;
                border-radius: 14px;
            }
            .login-header {
                padding: 12px 4px 10px 4px;
                border-radius: 10px;
            }
            .login-logo {
                width: 40px; height: 40px;
            }
            .system-name {
                font-size: 1.3rem;
            }
        }
        /* Hide browser's built-in password reveal (eye) icon in all browsers */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none !important;
        }
        input[type="password"]::-webkit-credentials-auto-fill-button,
        input[type="password"]::-webkit-input-decoration,
        input[type="password"]::-webkit-input-clear-button,
        input[type="password"]::-webkit-input-password-reveal-button {
            display: none !important;
        }
        .alert-login-error {
            margin-bottom: 1.2rem;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 500;
            color: #fff;
            background: linear-gradient(90deg, #dc3545 80%, #ff6f6f 100%);
            box-shadow: 0 2px 8px rgba(220,53,69,0.08);
            border: none;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.9rem 1.2rem;
            animation: fadeInDown 0.5s;
        }
        .alert-login-error i {
            font-size: 1.3rem;
            opacity: 0.85;
        }
        @keyframes fadeInDown {
            0% { opacity: 0; transform: translateY(-20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        /* Toast notification styles */
        .login-toast {
            position: fixed;
            top: 32px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 280px;
            max-width: 90vw;
            background: linear-gradient(90deg, #dc3545 80%, #ff6f6f 100%);
            color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(220,53,69,0.13);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1.08rem;
            font-weight: 500;
            animation: fadeInDown 0.5s;
        }
        .login-toast i {
            font-size: 1.3rem;
            opacity: 0.85;
        }
        @keyframes fadeInDown {
            0% { opacity: 0; transform: translate(-50%, -20px); }
            100% { opacity: 1; transform: translate(-50%, 0); }
        }
    </style>
</head>
<body>
    <?php
    $error_message = '';
    if (isset($_SESSION['login_error'])) {
        $error_message = $_SESSION['login_error'];
        unset($_SESSION['login_error']);
    } elseif (isset($_GET['error']) && $_GET['error']) {
        $error_message = $_GET['error'];
    }
    if ($error_message): ?>
        <div class="login-toast" id="loginToast">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    <div class="background-gradient"></div>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo"><img src="image/Untitled.jpg" alt="Sulivan Logo" onerror="this.src='uploads/default-logo.png'"></div>
                <h2 class="system-name">E-VOTE!</h2>
                <p class="system-tagline">Sulivan E-Voting System </p>
            </div>
            <form action="auth/login.php" method="POST" class="needs-validation">
                <div class="form-floating mb-4">
                    <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email">
                    <label for="email">Email Address</label>
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                    <label for="password">Password</label>
                    <span class="toggle-password" id="togglePassword">
                        <!-- Eye-slash SVG (hidden by default) -->
                        <svg id="eyeSlashIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#5558CD" stroke-width="2"><path d="M17.94 17.94A10.06 10.06 0 0 1 12 19c-5 0-9.27-3.11-11-7.5a12.32 12.32 0 0 1 4.73-5.73M6.53 6.53A9.77 9.77 0 0 1 12 5c5 0 9.27 3.11 11 7.5a12.3 12.3 0 0 1-2.09 3.26M1 1l22 22" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.53 9.53A3.5 3.5 0 0 0 12 16a3.5 3.5 0 0 0 2.47-6.47" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <!-- Eye SVG (hidden by default) -->
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#5558CD" stroke-width="2" style="display:none"><ellipse cx="12" cy="12" rx="10" ry="7.5"/><circle cx="12" cy="12" r="3.5"/></svg>
                    </span>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const eye = document.getElementById('eyeIcon');
            const eyeSlash = document.getElementById('eyeSlashIcon');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            if (type === 'text') {
                eye.style.display = '';
                eyeSlash.style.display = 'none';
            } else {
                eye.style.display = 'none';
                eyeSlash.style.display = '';
            }
        });

        // Auto-dismiss login toast after 4 seconds
        window.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('loginToast');
            if (toast) {
                setTimeout(function() {
                    toast.style.transition = 'opacity 0.5s';
                    toast.style.opacity = 0;
                    setTimeout(function() { toast.remove(); }, 500);
                }, 4000);
            }
        });
    </script>
</body>
</html>
