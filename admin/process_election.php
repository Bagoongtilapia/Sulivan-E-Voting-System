<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    header('Location: election_results.php?error=' . urlencode('Unauthorized access.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'authenticate_results') {
        try {
            // Update the authentication status
            $stmt = $pdo->prepare("
                UPDATE election_status 
                SET is_result_authenticated = TRUE 
                WHERE status = 'Ended' 
                ORDER BY id DESC 
                LIMIT 1
            ");
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                header('Location: election_results.php?success=' . urlencode('Election results have been authenticated successfully.'));
            } else {
                header('Location: election_results.php?error=' . urlencode('Unable to authenticate results. Please ensure the election has ended.'));
            }
        } catch (PDOException $e) {
            error_log("Error authenticating results: " . $e->getMessage());
            header('Location: election_results.php?error=' . urlencode('An error occurred while authenticating the results.'));
        }
    } else {
        header('Location: election_results.php?error=' . urlencode('Invalid action.'));
    }
    exit();
} else {
    header('Location: election_results.php');
    exit();
}
?>
