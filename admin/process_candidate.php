<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Function to handle image upload
function handleImageUpload($file) {
    $targetDir = '../uploads/candidates/';
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . '_' . basename($file['name']);
    $targetPath = $targetDir . $fileName;
    $imageFileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        throw new Exception('File is not an image.');
    }

    // Check file size (5MB max)
    if ($file['size'] > 5000000) {
        throw new Exception('File is too large. Maximum size is 5MB.');
    }

    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        throw new Exception('Only JPG, JPEG, PNG & GIF files are allowed.');
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/candidates/' . $fileName;
    }

    throw new Exception('Failed to upload image.');
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    if ($_SESSION['user_role'] !== 'Super Admin') {
        header('Location: manage_candidates.php?error=Only Super Admin can delete candidates');
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

        header('Location: manage_candidates.php?success=Candidate removed successfully');
    } catch (PDOException $e) {
        header('Location: manage_candidates.php?error=Failed to remove candidate');
    }
    exit();
}

// Handle POST request (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            // Edit existing candidate
            $candidate_id = $_POST['candidate_id'];
            $position_id = $_POST['position_id'];
            $platform = $_POST['platform'];

            if (!empty($_FILES['image']['name'])) {
                // Get old image path
                $stmt = $pdo->prepare("SELECT image_path FROM candidates WHERE id = ?");
                $stmt->execute([$candidate_id]);
                $oldImage = $stmt->fetch();

                // Upload new image
                $imagePath = handleImageUpload($_FILES['image']);

                // Delete old image
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

            header('Location: manage_candidates.php?success=Candidate updated successfully');
        } else {
            // Add new candidate
            $name = trim($_POST['name']);
            $position_id = $_POST['position_id'];
            $platform = $_POST['platform'];

            if (empty($name)) {
                throw new Exception('Candidate name is required');
            }

            // Handle image upload
            $imagePath = handleImageUpload($_FILES['image']);

            // Insert the candidate directly
            $stmt = $pdo->prepare("INSERT INTO candidates (name, position_id, platform, image_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $position_id, $platform, $imagePath]);

            header('Location: manage_candidates.php?success=Candidate added successfully');
        }
    } catch (Exception $e) {
        header('Location: manage_candidates.php?error=' . urlencode($e->getMessage()));
    }
    exit();
}

header('Location: manage_candidates.php');
exit();
?>
