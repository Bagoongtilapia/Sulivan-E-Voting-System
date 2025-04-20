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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .otp-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(57, 60, 178, 0.1);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .otp-input {
            letter-spacing: 1em;
            text-align: center;
            font-size: 1.5em;
            padding: 10px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="otp-container">
            <div class="text-center mb-4">
                <h2>OTP Verification</h2>
                <p>Please enter the verification code sent to your email</p>
            </div>
            
            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>

            <form action="verify_otp.php" method="POST">
                <div class="mb-4">
                    <input type="text" class="form-control otp-input" 
                           name="otp" maxlength="6" required 
                           pattern="[0-9]{6}"
                           placeholder="Enter OTP">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        Verify OTP
                    </button>
                </div>

            </form>
            
        </div>
    </div>
</body>
</html>
