<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    if (!in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
        header('Location: manage_voters.php?error=Unauthorized action: Only Admins can delete voters');
        exit();
    }

    $id = $_GET['id'];
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
                // First, remove their votes (if any)
                $stmt = $pdo->prepare("DELETE FROM votes WHERE student_id = ?");
                $stmt->execute([$id]);
                
                // Get the candidate name from users table
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $userName = $stmt->fetchColumn();
                
                // Then remove them from candidates if they exist (matching by name)
                if ($userName) {
                    $stmt = $pdo->prepare("DELETE FROM candidates WHERE name = ?");
                    $stmt->execute([$userName]);
                }
                
                // Finally, delete the user account
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Student'");
                if (!$stmt->execute([$id])) {
                    throw new PDOException("Failed to delete user record");
                }

                // If we got here, everything worked
                $pdo->commit();
                header('Location: manage_voters.php?success=Voter successfully deleted from the system');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Detailed error while deleting voter: " . $e->getMessage());
                
                // Check for specific error conditions
                if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                    header('Location: manage_voters.php?error=' . urlencode('Cannot delete voter due to database constraints. Please contact system administrator.'));
                } else {
                    header('Location: manage_voters.php?error=' . urlencode('Failed to delete voter: ' . $e->getMessage()));
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
            $pdo->rollBack();
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
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($name) || empty($email)) {
            header('Location: manage_voters.php?error=Name and email are required');
            exit();
        }

        try {
            if (isset($_POST['action']) && $_POST['action'] === 'edit') {
                // Edit existing voter
                $voter_id = $_POST['voter_id'];
                if (!empty($password)) {
                    // Update with new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'Student'");
                    $stmt->execute([$name, $email, $hashed_password, $voter_id]);
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'Student'");
                    $stmt->execute([$name, $email, $voter_id]);
                }
                header('Location: manage_voters.php?success=Voter updated successfully');
            } else {
                // Add new voter
                if (empty($password)) {
                    header('Location: manage_voters.php?error=Password is required for new voters');
                    exit();
                }
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'Student')");
                $stmt->execute([$name, $email, $hashed_password]);
                header('Location: manage_voters.php?success=Voter added successfully');
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
