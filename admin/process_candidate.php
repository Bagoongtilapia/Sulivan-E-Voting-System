<?php
session_start();
require_once '../config/database.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Function to handle image upload
function handleImageUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    $uploadDir = '../uploads/candidates/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/candidates/' . $fileName;
    }

    throw new Exception('Failed to upload image');
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    if ($_SESSION['user_role'] !== 'Super Admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Only Super Admin can delete candidates']);
        exit();
    }

    $id = $_GET['id'];
    try {
        // Get the image path before deleting
        $stmt = $pdo->prepare("SELECT image_path FROM candidates WHERE id = ?");
        $stmt->execute([$id]);
        $candidate = $stmt->fetch();

        // Delete the candidate
        $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->execute([$id]);

        // Delete the image file if it exists
        if (!empty($candidate['image_path'])) {
            $imagePath = '../' . $candidate['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Log the error
        error_log("Error in process_candidate.php: " . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'An error occurred while deleting the candidate: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Handle POST request (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            // Edit existing candidate
            if (!isset($_POST['candidate_id']) || !isset($_POST['position_id'])) {
                throw new Exception('Missing required fields');
            }

            $candidate_id = $_POST['candidate_id'];
            $position_id = $_POST['position_id'];
            $platform = $_POST['platform'] ?? '';

            // Verify candidate exists
            $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
            $stmt->execute([$candidate_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Candidate not found');
            }

            // Validate name format if name is being updated
            if (isset($_POST['name'])) {
                $name = trim($_POST['name']);
                if (!preg_match('/^[A-Za-z\s]+$/', $name)) {
                    throw new Exception('Candidate name should only contain letters and spaces');
                }
            }

            if (!empty($_FILES['image']['name'])) {
                // Get old image path
                $stmt = $pdo->prepare("SELECT image_path FROM candidates WHERE id = ?");
                $stmt->execute([$candidate_id]);
                $oldImage = $stmt->fetch();

                // Upload new image
                $imagePath = handleImageUpload($_FILES['image']);

                // Delete old image if it exists
                if (!empty($oldImage['image_path'])) {
                    $oldImagePath = '../' . $oldImage['image_path'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                // Update with new image
                $stmt = $pdo->prepare("UPDATE candidates SET position_id = ?, platform = ?, image_path = ? WHERE id = ?");
                $stmt->execute([$position_id, $platform, $imagePath, $candidate_id]);
            } else {
                // Update without changing image
                $stmt = $pdo->prepare("UPDATE candidates SET position_id = ?, platform = ? WHERE id = ?");
                $stmt->execute([$position_id, $platform, $candidate_id]);
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            // Add new candidate
            if (!isset($_POST['name']) || !isset($_POST['position_id'])) {
                throw new Exception('Missing required fields');
            }

            $name = trim($_POST['name']);
            $position_id = $_POST['position_id'];
            $platform = $_POST['platform'] ?? '';

            if (empty($name)) {
                throw new Exception('Candidate name is required');
            }

            // Validate name format (letters and spaces only)
            if (!preg_match('/^[A-Za-z\s]+$/', $name)) {
                throw new Exception('Candidate name should only contain letters and spaces');
            }

            // Handle image upload
            $imagePath = '';
            if (isset($_FILES['image'])) {
                $imagePath = handleImageUpload($_FILES['image']);
            }

            // Insert the candidate
            $stmt = $pdo->prepare("INSERT INTO candidates (name, position_id, platform, image_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $position_id, $platform, $imagePath]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        // Log the error
        error_log("Error in process_candidate.php: " . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'An error occurred while processing the candidate: ' . $e->getMessage()
        ]);
    }
    exit();
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit();
