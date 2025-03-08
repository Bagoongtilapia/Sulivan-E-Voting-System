<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Get form data
    $id = $_POST['candidateId'];
    $name = $_POST['name'];
    $position_id = $_POST['position'];
    $platform = $_POST['platform'];

    // Start with basic update query
    $sql = "UPDATE candidates SET name = ?, position_id = ?, platform = ?";
    $params = [$name, $position_id, $platform];

    // Check if a new image was uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/candidates/';
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png'];

        // Validate file extension
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid file type. Only JPG, JPEG, and PNG files are allowed.');
        }

        // Generate unique filename
        $newFilename = uniqid('candidate_') . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFilename;

        // Move uploaded file
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            // Add image to update query
            $sql .= ", image = ?";
            $params[] = $newFilename;
        }
    }

    // Complete the query and add WHERE clause
    $sql .= " WHERE id = ?";
    $params[] = $id;

    // Prepare and execute the update
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
