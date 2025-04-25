<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Voting System - Login</title>
    <link rel="icon" type="image/x-icon" href="./image/favicon.ico">
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
            background: linear-gradient(135deg, #E8E9FF 0%, #F8F9FF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(57, 60, 178, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        .login-header {
            background: var(--gradient-primary);
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e1e1e1;
            background-image: none !important;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(57, 60, 178, 0.25);
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: none !important;
            padding-right: 12px !important;
        }
        .form-control.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220,53,69,0.25);
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 12px 25px;
            font-weight: 600; 
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2A2D8F, #393CB2);
        }
        .input-group-text {
            background: var(--accent-color);
            border-color: #dee2e6;
            color: var(--primary-color);
        }
        .system-name {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        .system-tagline {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        .text-center {
            text-align: center;
        }
        .d-grid {
            display: grid;
            place-items: center;
        }

        .input-group input {
            padding-right: 40px; /* Reserve space for the icon */
        }

        .input-group i {
            transition: opacity 0.2s;
        }

        
        /* Show envelope icon on hover */
        .input-group:hover input[type="email"] ~ i {
            opacity: 1;
        }

        /* Ensure password toggle icon always stays visible */
        .input-group input[type="password"] ~ #togglePassword {
            opacity: 1 !important;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h2 class="system-name">E-VOTE!</h2>
                <p class="system-tagline">Sulivan E-Voting System </p>
            </div>
            <form action="auth/login.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="email" name="email" required 
                               placeholder="Enter your email">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required 
                               placeholder="Enter your password">
                        <i class="fas fa-eye-slash" id="togglePassword"></i>
                    </div>
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
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form validation
        (function () {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>
