<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Voting System - Login</title>
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
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin: 0 auto;
        }
        .login-container:hover {
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
            color: #4a4a4a;
        }
        .logo h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e1e1e1;
            background-image: none !important;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
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
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            width: 100%;
            margin-top: 20px;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #764ba2, #667eea);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .text-center {
            text-align: center;
        }
        .d-grid {
            display: grid;
            place-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <h2>E-VOTE!</h2>
                <p class="text-muted">Welcome to the E-Voting System</p>
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
