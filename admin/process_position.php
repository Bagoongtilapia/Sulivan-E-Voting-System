<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'add':
                $position_name = trim($_POST['position_name']);
                $max_votes = intval($_POST['max_votes']);

                if (empty($position_name)) {
                    header('Location: manage_positions.php?error=Position name cannot be empty');
                    exit();
                }

                if ($max_votes < 1) {
                    header('Location: manage_positions.php?error=Maximum votes must be at least 1');
                    exit();
                }

                // Add new position with max_votes
                $stmt = $pdo->prepare("INSERT INTO positions (position_name, max_votes) VALUES (?, ?)");
                $stmt->execute([$position_name, $max_votes]);
                header('Location: manage_positions.php?success=Position added successfully');
                break;

            case 'edit':
                $position_name = trim($_POST['position_name']);
                $max_votes = intval($_POST['max_votes']);
                $position_id = intval($_POST['position_id']);

                if (empty($position_name)) {
                    header('Location: manage_positions.php?error=Position name cannot be empty');
                    exit();
                }

                if ($max_votes < 1) {
                    header('Location: manage_positions.php?error=Maximum votes must be at least 1');
                    exit();
                }

                // Edit existing position
                $stmt = $pdo->prepare("UPDATE positions SET position_name = ?, max_votes = ? WHERE id = ?");
                $stmt->execute([$position_name, $max_votes, $position_id]);
                header('Location: manage_positions.php?success=Position updated successfully');
                break;

            case 'delete':
                $position_id = intval($_POST['position_id']);

                // Check if position exists
                $stmt = $pdo->prepare("SELECT id FROM positions WHERE id = ?");
                $stmt->execute([$position_id]);
                if (!$stmt->fetch()) {
                    header('Location: manage_positions.php?error=Position not found');
                    exit();
                }

                // Check if position has candidates
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE position_id = ?");
                $stmt->execute([$position_id]);
                if ($stmt->fetchColumn() > 0) {
                    header('Location: manage_positions.php?error=Cannot delete position with candidates. Remove candidates first.');
                    exit();
                }

                // Delete position
                $stmt = $pdo->prepare("DELETE FROM positions WHERE id = ?");
                $stmt->execute([$position_id]);
                header('Location: manage_positions.php?success=Position deleted successfully');
                break;

            default:
                header('Location: manage_positions.php?error=Invalid action');
                exit();
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            header('Location: manage_positions.php?error=Position name already exists');
        } else {
            header('Location: manage_positions.php?error=Database error: ' . $e->getMessage());
        }
    }
    exit();
}

header('Location: manage_positions.php');
exit();
