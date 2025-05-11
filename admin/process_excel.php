<?php
session_start();
require_once '../config/database.php';
require '../vendor/autoload.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';
require '../vendor/phpmailer/phpmailer/src/Exception.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Check if election is in Pre-Voting phase
$stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
$electionStatus = $stmt->fetchColumn();

if ($electionStatus !== 'Pre-Voting') {
    header('Location: manage_voters.php?error=Voter management is disabled during voting phase');
    exit();
}

// Function to generate random password
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header('Location: manage_voters.php?error=File upload failed');
        exit();
    }

    // Check file type
    $allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!in_array($file['type'], $allowedTypes)) {
        header('Location: manage_voters.php?error=Invalid file type. Please upload an Excel file');
        exit();
    }

    try {
        // Load the Excel file
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Get header row
        $headers = array_map('strtolower', array_shift($rows));
        
        // Find column indices for required fields
        $nameIndex = array_search('full name', $headers);
        $emailIndex = array_search('email address', $headers);
        $lrnIndex = array_search('lrn', $headers);

        // If exact matches not found, try partial matches
        if ($nameIndex === false) {
            foreach ($headers as $index => $header) {
                if (strpos($header, 'name') !== false) {
                    $nameIndex = $index;
                    break;
                }
            }
        }
        if ($emailIndex === false) {
            foreach ($headers as $index => $header) {
                if (strpos($header, 'email') !== false) {
                    $emailIndex = $index;
                    break;
                }
            }
        }
        if ($lrnIndex === false) {
            foreach ($headers as $index => $header) {
                if (strpos($header, 'lrn') !== false) {
                    $lrnIndex = $index;
                    break;
                }
            }
        }

        // Validate that we found all required columns
        if ($nameIndex === false || $emailIndex === false || $lrnIndex === false) {
            $missing = [];
            if ($nameIndex === false) $missing[] = 'Name';
            if ($emailIndex === false) $missing[] = 'Email';
            if ($lrnIndex === false) $missing[] = 'LRN';
            header('Location: manage_voters.php?error=Missing required columns: ' . implode(', ', $missing));
            exit();
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        // Start transaction
        $pdo->beginTransaction();

        foreach ($rows as $rowIndex => $row) {
            $name = trim($row[$nameIndex] ?? '');
            $email = trim($row[$emailIndex] ?? '');
            $lrn = trim($row[$lrnIndex] ?? '');

            // Skip empty rows
            if (empty($name) && empty($email) && empty($lrn)) {
                continue;
            }

            // Validate data
            if (empty($name) || empty($email) || empty($lrn)) {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . " has missing data: Name=" . ($name ?: 'empty') . ", Email=" . ($email ?: 'empty') . ", LRN=" . ($lrn ?: 'empty');
                continue;
            }

            // Validate name (letters and spaces only)
            if (!preg_match('/^[A-Za-z\s]+$/', $name)) {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . ": Full name should only contain letters and spaces: $name";
                continue;
            }

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . " has invalid email format: $email";
                continue;
            }

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . ": Email already exists: $email";
                continue;
            }

            // Check if LRN already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE lrn = ?");
            $stmt->execute([$lrn]);
            if ($stmt->fetch()) {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . ": LRN already exists: $lrn";
                continue;
            }

            // Insert new voter
            $tempPassword = generatePassword();
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, lrn, role, password) VALUES (?, ?, ?, 'Student', ?)");
            if ($stmt->execute([$name, $email, $lrn, $hashedPassword])) {
                // Send email with login credentials
                try {
                    $mail = new PHPMailer(true);
                    
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'sulivannationalhighschool@gmail.com';
                    $mail->Password = 'admf ihhi fruj jlcu';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Recipients
                    $mail->setFrom('sulivannationalhighschool@gmail.com', 'E-VOTE');
                    $mail->addAddress($email, $name);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Your New Account Details';
                    $mail->Body = "
                        Hi $name,<br><br>
                        Your account has been created.<br>
                        Temporary Password: <br>
                        <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                            <h1 style='color: #393CB2; margin: 0; letter-spacing: 5px;'>$tempPassword</h1>
                        </div>
                        Please log in and reset your password immediately.<br><br>
                    ";

                    $mail->send();
                    $successCount++;
                } catch (Exception $e) {
                    // Log the error but don't show technical details to user
                    error_log("Failed to send email: " . $mail->ErrorInfo);
                    $errorCount++;
                    $errors[] = "Row " . ($rowIndex + 2) . ": Voter added successfully but failed to send email notification";
                }
            } else {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . ": Failed to insert: $name";
            }
        }

        // Commit transaction
        $pdo->commit();

        // Prepare success/error message
        $message = "<div class='alert alert-success mb-3'>";
        $message .= "<i class='bx bx-check-circle me-2'></i>";
        $message .= "Voters Imported Successfully. $successCount new voters to the system.";
        $message .= "</div>";

        if ($errorCount > 0) {
            $message .= "<div class='alert alert-warning mb-3'>";
            $message .= "<i class='bx bx-error-circle me-2'></i>";
            $message .= "We couldn't add $errorCount voters. Here's why:";
            $message .= "</div>";

            if (!empty($errors)) {
                $message .= "<div class='alert alert-info'>";
                $message .= "<h6 class='mb-2'><i class='bx bx-info-circle me-2'></i>Details:</h6>";
                $message .= "<ul class='mb-0'>";
                foreach ($errors as $error) {
                    // Make error messages more user-friendly
                    $error = str_replace("Row ", "Line ", $error);
                    $error = str_replace("has missing data", "is missing some information", $error);
                    $error = str_replace("has invalid email format", "has an incorrect email format", $error);
                    $error = str_replace("Email already exists", "This email is already registered", $error);
                    $error = str_replace("LRN already exists", "This LRN is already registered", $error);
                    $error = str_replace("Failed to insert", "Could not add", $error);
                    $error = str_replace("Voter added successfully but failed to send email notification", "Account created but couldn't send the email", $error);
                    $message .= "<li>$error</li>";
                }
                $message .= "</ul>";
                $message .= "</div>";
            }
        }

        header('Location: manage_voters.php?success=' . urlencode($message));
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: manage_voters.php?error=' . urlencode('Error processing Excel file: ' . $e->getMessage()));
        exit();
    }
} else {
    header('Location: manage_voters.php?error=No file uploaded');
    exit();
} 