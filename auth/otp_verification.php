<?php
session_start();
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_user_email'])) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - E-Voting System</title>
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
            background: linear-gradient(135deg, #E8E9FF 0%, #F8F9FF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            color: white;
        }

        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .otp-container {
            background: #2A2D8F;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(57, 60, 178, 0.1);
            width: 90%;
            max-width: 400px;
            min-width: 320px;
            padding: 40px 30px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0 auto;
        }


        .otp-title {
            font-size: 1.2em;
            color: white;
            margin-bottom: 10px;
            text-align: center;
        }

        .otp-email {
            color: #fbfbfb;
            text-align: center;
            margin-bottom: 25px;
            font-size: 0.9em;
        }

        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 25px;
        }

        .otp-inputs input {
            width: 40px;
            height: 40px;
            text-align: center;
            border: 1px solid ;
            background: transparent;
            border-radius: 8px;
            color: white;
            font-size: 1.2em;
        }

        .otp-inputs input:focus {
            outline: none;
            border-color: #393CB2;
            box-shadow: 0 0 0 2px rgba(57, 60, 178, 0.2);
        }

        .spam-notice {
            text-align: center;
            color: #fbfbfb;
            font-size: 0.8em;
            margin-top: 20px;
        }

        .spam-notice span {
            color:rgb(187, 184, 184);
            text-decoration: none;
        }

        .alert {
            background: #2a1a1a;
            border: 1px solid #ff4444;
            color: #ff4444;
            margin-bottom: 20px;
        }

        .alert.alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
            color: #28a745;
        }

        .resend-button {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
            width: 100%;
            margin-top: 20px;
        }

        .resend-button:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .resend-button.show {
            display: block;
        }

        .attempts-counter {
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            font-size: 0.9em;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="otp-container">
           
            <div class="otp-title">Check your email</div>
            <div class="otp-email">Enter the code sent to <?php echo htmlspecialchars($_SESSION['temp_user_email']); ?></div>
            
            <?php if (isset($_GET['error'])): ?>
            <div class="alert" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_GET['message']); ?>
            </div>
            <?php endif; ?>

            <form action="verify_otp.php" method="POST" id="otpForm">
                <div class="otp-inputs">
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="hidden" name="otp" id="otpFinal">
                </div>
            </form>

            <div class="spam-notice">
                Can't find the email? <span>Check your spam folder</span>
            </div>

            <?php if (isset($_SESSION['otp_attempts']) && $_SESSION['otp_attempts'] > 0): ?>
            <div class="attempts-counter">
                <?php echo (3 - $_SESSION['otp_attempts']); ?> attempts remaining
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['show_resend']) && $_GET['show_resend'] === 'true'): ?>
            <form action="send_otp.php" method="POST">
                <button type="submit" class="resend-button show">
                    Resend New OTP
                </button>
            </form>
            <?php endif; ?>
        </div>
        
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.otp-inputs input[type="text"]');
            const form = document.getElementById('otpForm');
            const otpFinal = document.getElementById('otpFinal');

            // Auto-focus first input on page load
            inputs[0].focus();

            // Auto-focus next input and validate numbers only
            inputs.forEach((input, index) => {
                input.addEventListener('input', function() {
                    // Remove any non-numeric characters
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    if (this.value.length === 1) {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });

                // Handle backspace
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });

                // Prevent paste and copy events
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                });

                input.addEventListener('copy', function(e) {
                    e.preventDefault();
                });

                input.addEventListener('cut', function(e) {
                    e.preventDefault();
                });
            });

            // Submit form when all inputs are filled
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    const allFilled = Array.from(inputs).every(input => input.value.length === 1);
                    if (allFilled) {
                        otpFinal.value = Array.from(inputs).map(input => input.value).join('');
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>
