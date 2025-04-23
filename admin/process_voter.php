<?php
session_start();
require_once '../config/database.php';
// require '../vendor/autoload.php'; // For PHPMailer
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Function to generate random password
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Handle DELETE request
if (($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') ||
    ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete')) {
    
    $id = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['voter_id']) ? $_POST['voter_id'] : null);
    
    if (!$id) {
        header('Location: manage_voters.php?error=No voter ID provided');
        exit();
    }

    if (!in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
        header('Location: manage_voters.php?error=Unauthorized action: Only Admins can delete voters');
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // First, check if the user exists and is a student
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'Student'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new PDOException("Voter not found or is not a student");
        }

        // Check the election status
        $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
        $electionStatus = $stmt->fetchColumn();

        if ($electionStatus === 'Voting') {
            $pdo->rollBack();
            header('Location: manage_voters.php?error=' . urlencode('Cannot delete voters during the voting phase. Please wait until the pre-voting phase to manage voters.'));
            exit();
        } else if ($electionStatus === 'Ended') {
            $pdo->rollBack();
            header('Location: manage_voters.php?error=' . urlencode('Cannot delete voters after the election has ended. Please wait until the next pre-voting phase.'));
            exit();
        }

        if ($electionStatus === 'Pre-Voting') {
            try {
                // Make sure no transaction is active before starting a new one
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                // Start transaction
                $pdo->beginTransaction();

                // 1. First, delete from otp_codes table
                $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?");
                $stmt->execute([$id]);
                
                // 2. Delete from votes table
                $stmt = $pdo->prepare("DELETE FROM votes WHERE student_id = ?");
                $stmt->execute([$id]);
                
                // 3. Get the user's name before deletion
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $userName = $stmt->fetchColumn();
                
                // 4. Delete from candidates table if they are a candidate
                if ($userName) {
                    $stmt = $pdo->prepare("DELETE FROM candidates WHERE name = ?");
                    $stmt->execute([$userName]);
                }
                
                // 5. Finally, delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Student'");
                if (!$stmt->execute([$id])) {
                    throw new PDOException("Failed to delete user record");
                }

                // If we got here, everything worked
                $pdo->commit();
                header('Location: manage_voters.php?success=Voter successfully deleted from the system');
                exit();
            } catch (PDOException $e) {
                // Make sure to rollback if there's an active transaction
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                error_log("Detailed error while deleting voter: " . $e->getMessage());
                
                // More specific error handling
                if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                    header('Location: manage_voters.php?error=' . urlencode('Cannot delete voter: They have related records in the system.'));
                } else {
                    header('Location: manage_voters.php?error=' . urlencode('Failed to delete voter. Please try again.'));
                }
                exit();
            }
        } else {
            // For non-pre-voting phases
            $stmt = $pdo->prepare("SELECT 
                (SELECT COUNT(*) FROM votes WHERE student_id = ?) as has_voted,
                (SELECT COUNT(*) FROM candidates WHERE name = (SELECT name FROM users WHERE id = ?)) as is_candidate");
            $stmt->execute([$id, $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['has_voted'] && $result['is_candidate']) {
                header('Location: manage_voters.php?error=' . urlencode('Cannot delete: Voter is a candidate and has already cast their vote. Please wait for pre-voting phase.'));
            } else if ($result['has_voted']) {
                header('Location: manage_voters.php?error=' . urlencode('Cannot delete: Voter has already cast their vote. Please wait for pre-voting phase.'));
            } else if ($result['is_candidate']) {
                header('Location: manage_voters.php?error=' . urlencode('Cannot delete: Voter is registered as a candidate. Please wait for pre-voting phase.'));
            }
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            exit();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in voter deletion process: " . $e->getMessage());
        header('Location: manage_voters.php?error=' . urlencode('System Error: ' . $e->getMessage()));
    }
    exit();
}

// Handle POST request (Add/Edit voter)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check election status first
    $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
    $electionStatus = $stmt->fetchColumn();

    if ($electionStatus === 'Voting') {
        header('Location: manage_voters.php?error=' . urlencode('Cannot add new voters during the voting phase. Please wait until the pre-voting phase to add voters.'));
        exit();
    } else if ($electionStatus === 'Ended') {
        header('Location: manage_voters.php?error=' . urlencode('Cannot add new voters after the election has ended. Please wait until the next pre-voting phase.'));
        exit();
    }

    // Only proceed if we're in pre-voting phase
    if ($electionStatus === 'Pre-Voting') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);

        if (empty($name) || empty($email)) {
            header('Location: manage_voters.php?error=Name and email are required');
            exit();
        }

        try {
            if (isset($_POST['action']) && $_POST['action'] === 'edit') {
                // Edit existing voter
                $voter_id = $_POST['voter_id'];
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'Student'");
                $stmt->execute([$name, $email, $voter_id]);
                header('Location: manage_voters.php?success=Voter updated successfully');
            } else {
                // Add new voter
                $tempPassword = generatePassword();
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                // Insert the new user with hashed password
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'Student')");
                if ($stmt->execute([$name, $email, $hashedPassword])) {
                    // Send email notification
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'sulivannationalhighschool@gmail.com';
                        $mail->Password = 'nqhb kdea brfc xwvw';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;

                        $mail->setFrom('sulivannationalhighschool@gmail.com', 'System Admin');
                        $mail->addAddress($email, $name);
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
                            <a href='http://localhost/login.php'>Log in here</a>
                        ";
                        
                        $mail->send();
                        header('Location: manage_voters.php?success=Voter added successfully and login credentials sent via email');
                    } catch (Exception $e) {
                        // Log the error but don't show technical details to user
                        error_log("Failed to send email: " . $mail->ErrorInfo);
                        header('Location: manage_voters.php?success=Voter added successfully but failed to send email notification');
                    }
                } else {
                    header('Location: manage_voters.php?error=Failed to add voter');
                }
                exit();
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry error
                header('Location: manage_voters.php?error=Email already exists');
            } else {
                header('Location: manage_voters.php?error=Failed to process voter');
            }
        }
        exit();
    }
}

header('Location: manage_voters.php');
exit();
?>
