<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header('Location: ../index.php');
    exit();
}

// Process vote submission
if(isset($_POST['vote'])) {
    $votes = $_POST['vote'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Insert each vote
        foreach($votes as $position_id => $candidate_ids) {
            foreach($candidate_ids as $candidate_id) {
                $sql = "INSERT INTO votes (user_id, candidate_id, position_id) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['user_id'], $candidate_id, $position_id]);
            }
        }
        
        // Update voters status
        $sql = "UPDATE users SET voted = 1 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        // Store success message in session and redirect
        $_SESSION['message'] = array('type' => 'success', 'text' => 'Your vote has been recorded successfully!');
        header('Location: dashboard.php');
        exit();
        
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        $_SESSION['message'] = array('type' => 'error', 'text' => 'Error recording vote: ' . $e->getMessage());
        header('Location: dashboard.php');
        exit();
    }
}

// Display message if exists
if(isset($_SESSION['message'])) {
    $messageClass = ($_SESSION['message']['type'] === 'success') ? 'alert-success' : 'alert-danger';
    echo '<div class="alert ' . $messageClass . '">' . htmlspecialchars($_SESSION['message']['text']) . '</div>';
    unset($_SESSION['message']);
}

// Display vote message if exists
if(isset($_SESSION['vote_message'])) {
    echo '<div class="alert alert-info">' . htmlspecialchars($_SESSION['vote_message']) . '</div>';
    unset($_SESSION['vote_message']);
}

// Display status messages
if(isset($_GET['status'])) {
    if($_GET['status'] === 'success') {
        echo '<div class="alert alert-success">Your vote has been recorded successfully!</div>';
    } else if($_GET['status'] === 'error') {
        echo '<div class="alert alert-danger">An error occurred while recording your vote. Please try again.</div>';
    }
}

// Check if password needs to be changed
$stmt = $pdo->prepare("SELECT password_changed FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check election status
try {
    $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
    $electionStatus = $stmt->fetch(PDO::FETCH_COLUMN) ?? 'Pre-Voting';
} catch (PDOException $e) {
    error_log("Error fetching election status: " . $e->getMessage());
    $error = "Error fetching election status";
    $electionStatus = 'Unknown';
}

// Check if user has already voted
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $hasVoted = $stmt->fetchColumn() > 0;
} catch (PDOException $e) {
    error_log("Error checking vote status: " . $e->getMessage());
    $error = "Error checking vote status";
    $hasVoted = false;
}

// If password hasn't been changed and it's pre-voting period, redirect to change password
if (!$user['password_changed'] && $electionStatus === 'Pre-Voting') {
    header('Location: change_password.php');
    exit();
}

// Debug information
error_log("Session data: " . print_r($_SESSION, true));

// Get student's voting status
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $hasVoted = $stmt->fetchColumn() > 0;
    error_log("Has voted status for student {$_SESSION['user_id']}: " . ($hasVoted ? 'Yes' : 'No'));
} catch (PDOException $e) {
    error_log("Error checking voting status: " . $e->getMessage());
    $error = "Error checking voting status";
    $hasVoted = false;
}
// Check for success/error messages from URL parameters
$success = isset($_GET['success']) ? $_GET['success'] : null;
$error = isset($_GET['error']) ? $_GET['error'] : null;

// Get positions and candidates if in voting phase
$positions = [];
if ($electionStatus === 'Voting' && !$hasVoted) {
    try {
        $stmt = $pdo->query("
            SELECT 
                p.id as position_id,
                p.position_name,
                p.max_votes,
                c.id as candidate_id,
                c.name as candidate_name,
                c.image_path,
                c.platform
            FROM positions p
            LEFT JOIN candidates c ON p.id = c.position_id
            ORDER BY p.id, c.id
        ");
        error_log("Fetching positions and candidates query: " . $stmt->queryString);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("Row data: " . print_r($row, true));
            if (!isset($positions[$row['position_id']])) {
                $positions[$row['position_id']] = [
                    'id' => $row['position_id'],
                    'name' => $row['position_name'],
                    'max_votes' => $row['max_votes'],
                    'candidates' => []
                ];
            }
            if ($row['candidate_id']) {
                $image_path = $row['image_path'] ? '../' . $row['image_path'] : '../uploads/candidates/default.png';
                $positions[$row['position_id']]['candidates'][] = [
                    'id' => $row['candidate_id'],
                    'name' => $row['candidate_name'],
                    'image_url' => $image_path,
                    'platform' => $row['platform']
                ];
            }
        }
        error_log("Final positions array: " . print_r($positions, true));
    } catch (PDOException $e) {
        error_log("Error fetching positions and candidates: " . $e->getMessage());
        $error = "Error fetching positions and candidates";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Voting System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- BoxIcons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #393CB2;
            --primary-light: #5558CD;
            --primary-dark: #2A2D8F;
            --accent-color: #E8E9FF;
            --gradient-primary: linear-gradient(135deg, #393CB2, #5558CD);
            --card-shadow: 0 8px 24px rgba(57, 60, 178, 0.2);
        }

        body {
            background: linear-gradient(135deg, #E8E9FF 0%, #F8F9FF 100%);
            min-height: 100vh;
        }

        .navbar {
            background: var(--gradient-primary);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(57, 60, 178, 0.15);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            font-weight: 600;
            font-size: 1.3rem;
        }

        .navbar-brand img {
            width: 50px;
            height: 50px;
            margin-right: 12px;
            object-fit: cover;
            border-radius: 50%;
            background-color: #393CB2;
            padding: -10px;
            border: 2px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .navbar-brand:hover img {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            border-color: white;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            margin-bottom: 25px;
        }

        .card:hover {
            box-shadow: 0 12px 30px rgba(149, 157, 165, 0.3);
        }

        .status-badge {
            font-size: 0.95rem;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .voting-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
        }

        .position-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-light);
            text-align: center;
        }

        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .candidate-card {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1.25rem;
            background: white;
            box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
            display: flex;
            flex-direction: column;
            height: 100%;
            cursor: pointer;
            position: relative;
        }

        .candidate-card:hover {
            box-shadow: 0 1px 6px rgba(0,0,0,0.2);
        }

        .candidate-card.selected {
            border: 2px solid var(--primary-color);
            background-color: rgba(57, 60, 178, 0.05);
        }

        .candidate-card.selected::after {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--primary-color);
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .form-check-input {
            display: none;
        }

        .candidate-details {
            flex-grow: 1;
            padding-left: 15px;
            display: flex;
            flex-direction: column;
        }

        .details-top {
            margin-bottom: 10px;
        }

        .details-bottom {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-top: auto;
        }

        .btn-platform {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 3px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }

        .btn-platform:hover {
            background: var(--primary-light);
            color: white;
        }

        .btn-platform i {
            font-size: 0.9rem;
        }

        .candidate-name {
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .candidate-position {
            color: #636c72;
            font-size: 0.9rem;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .candidate-position i {
            color: var(--primary-light);
        }

        .btn-vote {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 3px;
            font-size: 1rem;
            font-weight: 600;
            box-shadow: none;
            transition: all 0.2s ease;
            margin-top: 30px;
        }

        .btn-vote:hover {
            background: var(--primary-light);
            color: white;
        }

        .voting-section {
            margin-bottom: 2rem;
        }

        .position-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-light);
        }

        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .candidate-image-container {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--primary-color);
        }

        .candidate-image {
            border-radius: 50%;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(57, 60, 178, 0.15);
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            border-bottom: none;
            padding: 1.25rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            background-color: #f4f6f9;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
            padding: 1rem 1.5rem;
        }

        .btn-default {
            background-color: #E8E9FF;
            color: var(--primary-color);
            border: none;
        }

        .btn-default:hover {
            background-color: #D8D9FF;
            color: var(--primary-dark);
        }

        .candidate-platform-header {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent-color);
        }

        .platform-content {
            color: #4A4B57;
            line-height: 1.6;
            font-size: 1rem;
            padding: 0.5rem;
        }
        
        .results-container {
            padding: 20px;
        }
        
        .winner-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.1);
        }
        
        .winner-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            background: rgba(57, 60, 178, 0.03);
        }
        
        .winner-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #393CB2;
        }
        
        .winner-details {
            flex-grow: 1;
        }
        
        .winner-details h4 {
            color: #393CB2;
            margin: 0 0 5px 0;
            font-size: 1.2rem;
        }
        
        .vote-count {
            color: #2A2D8F;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        
        .position-header {
            color: #393CB2;
            border-bottom: 2px solid #E8E9FF;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.5rem;
            text-align: center;
        }

        .winner-badge {
            background: linear-gradient(135deg, #393CB2, #5558CD);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 10px;
        }

        .election-status {
            background: white;
            border-radius: 12px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(57, 60, 178, 0.1);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .election-status::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
        }

        .election-status.voting::before {
            background: #28a745;
        }

        .election-status.ended::before {
            background: #dc3545;
        }

        .election-status.pre-voting::before {
            background: #ffc107;
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .status-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-indicator.voting {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-indicator.ended {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .status-indicator.pre-voting {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-indicator i {
            margin-right: 0.5rem;
            font-size: 1rem;
        }

        .preview-modal .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 15px 35px rgba(57, 60, 178, 0.15);
            background: #f8f9ff;
        }

        .preview-modal .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 1.75rem;
            border-bottom: none;
            position: relative;
            overflow: hidden;
        }

        .preview-modal .modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 30%;
            background: linear-gradient(rgba(255,255,255,0), rgba(255,255,255,0.1));
        }

        .preview-modal .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: 0.5px;
        }

        .preview-modal .modal-title i {
            font-size: 1.5rem;
        }

        .preview-modal .btn-close-white {   
            opacity: 0.8;
            transition: all 0.2s ease;
        }

        .preview-modal .btn-close-white:hover {
            opacity: 1;
        }

        .preview-modal .modal-body {
            padding: 2rem;
        }

        .preview-notice {
            background: linear-gradient(45deg, #fff8e1, #fff3cd);
            color: #856404;
            padding: 1.25rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 3px 10px rgba(133, 100, 4, 0.1);
        }

        .preview-notice i {
            font-size: 1.5rem;
            color: #f4b619;
        }

        .preview-position {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border: none;
            position: relative;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(57, 60, 178, 0.08);
            transition: all 0.3s ease;
        }

        .preview-position:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(57, 60, 178, 0.12);
        }

        .preview-position::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .preview-position h4 {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
            letter-spacing: 0.3px;
        }

        .preview-position h4 .position-count {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
            background: var(--accent-color);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
        }

        .preview-candidates {
            padding-left: 0;
        }

        .preview-candidate {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
            padding: 1rem;
            background: var(--accent-color);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .preview-candidate:last-child {
            margin-bottom: 0;
        }

        .preview-candidate:hover {
            transform: translateX(5px);
            background: #e0e1ff;
        }

        .preview-candidate i {
            color: var(--primary-color);
            font-size: 1.4rem;
            background: white;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(57, 60, 178, 0.15);
        }

        .preview-candidate .candidate-info {
            flex-grow: 1;
        }

        .preview-candidate .candidate-name {
            font-weight: 500;
            color: #333;
            margin: 0;
            font-size: 1.1rem;
        }

        .preview-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid #eef0ff;
        }

        .btn-modify {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.9rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-modify:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }

        .btn-confirm {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(46, 204, 113, 0.2);
        }

        .btn-confirm:hover {
            background: linear-gradient(45deg, #27ae60, #219a52);
            transform: translateY(-1px);
            color: white;
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
        }

        .btn-confirm i {
            font-size: 1.1rem;
        }
        
        .preview-candidate.no-selection {
            background: #f8f9fa;
            opacity: 0.8;
        }

        .preview-candidate.no-selection i {
            color: #6c757d;
            background: #e9ecef;
        }

        .preview-candidate.no-selection .candidate-name {
            color: #6c757d;
            font-style: italic;
        }
        
        .platform-modal-content .candidate-profile {
            text-align: center;
            padding: 1rem;
        }

        .candidate-name-lg {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            text-transform: capitalize;
        }
        
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
        }
        
        .modal-header {
            padding: 1.5rem 1.5rem 0;
        }
        
        .btn-close {
            opacity: 0.8;
            transition: all 0.3s ease;
            background-color: transparent;
        }

        /* Animation for modal */
        .modal.fade .modal-dialog {
            transform: scale(0.95);
            transition: transform 0.3s ease-out;
        }

        .modal.show .modal-dialog {
            transform: scale(1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand text-white" href="#">
                <img src="../image/Untitled.jpg" 
                     alt="E-Voting System" 
                     onerror="this.src='../uploads/default-logo.png'">
                E-Voting System
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3">
                    <i class='bx bx-user-circle me-1'></i>
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light">
                    <i class='bx bx-log-out-circle me-1'></i>
                    Sign out
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger animate-fadeInUp">
                <i class='bx bx-error-circle me-2'></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card animate-fadeInUp">
            <div class="card-body">
                <div class="election-status <?php echo strtolower($electionStatus); ?>">
                    <div class="status-header">
                        <h3>Election Status</h3>
                        <div class="status-indicator <?php echo strtolower($electionStatus); ?>">
                            <i class="bx <?php 
                                echo $electionStatus === 'Voting' ? 'bx-check-circle' : 
                                    ($electionStatus === 'Ended' ? 'bx-x-circle' : 'bx-time'); 
                            ?>"></i>
                            <?php echo $electionStatus; ?>
                        </div>
                    </div>
                </div>

                <?php if ($electionStatus === 'Pre-Voting'): ?>
                    <div class="alert alert-warning">
                        <i class='bx bx-time-five me-2'></i>
                        Voting is currently closed.. Please come back soon to cast your vote.
                    </div>
                <?php elseif ($electionStatus === 'Ended'): ?>
                    <div class="results-container">
                        <h2 class="text-center mb-4">Election Results</h2>
                        <?php
                        try {
                            // Get positions with candidates and vote counts
                            $sql = "SELECT 
                                p.id, 
                                p.position_name,
                                p.max_votes,
                                c.id as candidate_id,
                                c.name as candidate_name,
                                c.image_path,
                                c.platform,
                                (SELECT COUNT(*) FROM votes v 
                                 WHERE v.candidate_id = c.id) as vote_count
                            FROM positions p
                            LEFT JOIN candidates c ON p.id = c.position_id
                            ORDER BY p.id, vote_count DESC";
                            
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                            
                            $results = [];
                            
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                if(!isset($results[$row['id']])) {
                                    $results[$row['id']] = [
                                        'position_name' => $row['position_name'],
                                        'max_votes' => $row['max_votes'],
                                        'candidates' => []
                                    ];
                                }
                                
                                // Add all candidates with their vote counts
                                if ($row['candidate_id']) {
                                    $results[$row['id']]['candidates'][] = [
                                        'name' => htmlspecialchars($row['candidate_name']),
                                        'image_path' => $row['image_path'] ? '../' . $row['image_path'] : '../uploads/candidates/default.png',
                                        'platform' => htmlspecialchars($row['platform']),
                                        'votes' => (int)$row['vote_count']
                                    ];
                                }
                            }

                            // Display results
                            foreach($results as $position): ?>
                                <div class="winner-card">
                                    <h3 class="position-header"><?php echo htmlspecialchars($position['position_name']); ?></h3>
                                    <?php 
                                    // Sort candidates by vote count
                                    usort($position['candidates'], function($a, $b) {
                                        return $b['votes'] - $a['votes'];
                                    });

                                    foreach($position['candidates'] as $index => $candidate): 
                                        $isWinner = $index < $position['max_votes'];
                                    ?>
                                        <div class="winner-info">
                                            <img src="<?php echo htmlspecialchars($candidate['image_path']); ?>" 
                                                 class="winner-image" 
                                                 alt="<?php echo htmlspecialchars($candidate['name']); ?>"
                                                 onerror="this.src='../uploads/candidates/default.png'">
                                            <div class="winner-details">
                                                <h4>
                                                    <?php echo htmlspecialchars($candidate['name']); ?>
                                                    <?php if($isWinner): ?>
                                                        <span class="winner-badge">
                                                            <i class="fas fa-crown"></i> Winner
                                                        </span>
                                                    <?php endif; ?>
                                                </h4>
                                                <p class="vote-count">
                                                    <i class="fas fa-vote-yea"></i>
                                                    <?php echo $candidate['votes']; ?> vote<?php echo $candidate['votes'] != 1 ? 's' : ''; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach;
                            
                        } catch(PDOException $e) {
                            error_log("Error fetching election results: " . $e->getMessage());
                            echo '<div class="alert alert-danger">Error fetching election results: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        ?>
                    </div>
                <?php elseif ($hasVoted): ?>
                    <div class="alert alert-success">
                        <i class='bx bx-check-circle me-2'></i>
                        Thank you for voting! Your vote has been recorded.
                    </div>
                <?php elseif ($electionStatus === 'Voting'): ?>
                    <form action="cast_vote.php" method="POST" id="votingForm" onsubmit="return false;">
                        <?php foreach ($positions as $position): ?>
                            <div class="voting-section">
                                <h3 class="position-title"><?php echo htmlspecialchars($position['name']); ?></h3>
                                <div class="candidates-grid">
                                    <?php foreach ($position['candidates'] as $candidate): ?>
                                        <div class="candidate-card" onclick="selectCandidate(this, <?php echo $position['id']; ?>)">
                                            <div class="candidate-content">
                                                <div class="candidate-image-container">
                                                    <img src="<?php echo htmlspecialchars($candidate['image_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($candidate['name']); ?>" 
                                                         class="candidate-image"
                                                         onerror="this.src='../uploads/candidates/default.png'">
                                                </div>
                                                <div class="candidate-details">
                                                    <div class="details-top">
                                                        <h5 class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></h5>
                                                        <p class="candidate-position">
                                                            <span>Running for <?php echo htmlspecialchars($position['name']); ?></span>
                                                            <i class='fas fa-check-circle'></i>
                                                        </p>
                                                    </div>
                                                    <div class="details-bottom">
                                                        <button type="button" class="btn btn-platform" 
                                                                data-name="<?php echo htmlspecialchars($candidate['name']); ?>"
                                                                data-platform="<?php echo htmlspecialchars($candidate['platform']); ?>"
                                                                data-position="<?php echo htmlspecialchars($position['name']); ?>"
                                                                onclick="viewPlatform(this)">
                                                            <i class='fas fa-book'></i>
                                                            Platform
                                                        </button>
                                                    </div>
                                                </div>
                                                <input type="checkbox" 
                                                       name="vote[<?php echo $position['id']; ?>][]" 
                                                       value="<?php echo $candidate['id']; ?>" 
                                                       class="form-check-input">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="text-center">
                            <button type="button" class="btn btn-vote" onclick="previewVotes()">
                                <i class='bx bx-check-circle me-2'></i>Preview Your Vote
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Platform Modal -->
    <div class="modal fade" id="platformModal" tabindex="-1" role="dialog" aria-labelledby="platformModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header platform-header-bg">
                    <div class="modal-title-container">
                        <h4 class="modal-title" id="platformModalLabel">
                            <span id="modalPositionName" class="position-title"></span>
                        </h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="platform-modal-content">
                        <div class="candidate-profile">
                            <h3 id="modalCandidateName" class="candidate-name-lg"></h3>
                        </div>
                        <div class="platform-content">
                            <div class="platform-section">
                                <h4 class="platform-section-title">
                                    <i class='bx bx-target-lock me-2'></i>Platform & Vision
                                </h4>
                                <div class="platform-text-wrapper">
                                    <p id="modalPlatform" class="platform-text"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade preview-modal" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">
                        <i class='bx bx-check-square'></i>
                        Review Your Votes
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="preview-notice">
                        <i class='bx bx-info-circle'></i>
                        <span>Please review your selections carefully. You cannot change your votes after submission.</span>
                    </div>
                    <div id="previewContent"></div>
                    <div class="preview-buttons">
                        <button type="button" class="btn btn-confirm" onclick="submitVote()">
                            <i class='bx bx-check-circle'></i>Confirm and Submit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AdminLTE JS -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize all modals
        var platformModal = new bootstrap.Modal(document.getElementById('platformModal'));
        var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        
        function viewPlatform(button) {
            // Prevent the card selection
            event.stopPropagation();
            
            // Get data from button attributes
            const name = button.getAttribute('data-name');
            const platform = button.getAttribute('data-platform');
            const position = button.getAttribute('data-position');
            
            // Update modal content
            document.getElementById('modalCandidateName').textContent = name;
            document.getElementById('modalPositionName').textContent = position;
            document.getElementById('modalPlatform').textContent = platform || 'No platform information available.';
            
            // Show the modal
            platformModal.show();
        }

        function previewVotes() {
            const positions = <?php echo json_encode($positions); ?>;
            let preview = {};
            
            // First, initialize all positions in the preview
            for (const positionId in positions) {
                const position = positions[positionId];
                preview[position.name] = {
                    candidates: [],
                    maxVotes: position.max_votes,
                    positionName: position.name
                };
            }
            
            // Then collect selected candidates
            for (const positionId in positions) {
                const position = positions[positionId];
                const selectedCandidates = document.querySelectorAll(`input[name="vote[${positionId}][]"]:checked`);
                
                // Only check for maximum votes limit
                if (selectedCandidates.length > position.max_votes) {
                    toastr.warning(`You can only select ${position.max_votes} candidate(s) for ${position.name}`);
                    return;
                }
                
                // Add selected candidates to the preview
                if (selectedCandidates.length > 0) {
                    preview[position.name].candidates = Array.from(selectedCandidates).map(checkbox => {
                        const card = checkbox.closest('.candidate-card');
                        return card.querySelector('.candidate-name').textContent.trim();
                    });
                }
            }
            
            // Generate preview HTML
            let html = '<ul class="preview-list">';
            for (const positionName in preview) {
                const data = preview[positionName];
                html += `
                    <li class="preview-position">
                        <h4>${data.positionName}
                            <span class="position-count">${data.candidates.length} of ${data.maxVotes} selected</span>
                        </h4>`;
                
                // Only show candidates section if there are selections
                if (data.candidates.length > 0) {
                    html += '<div class="preview-candidates">';
                    data.candidates.forEach(candidate => {
                        html += `
                            <div class="preview-candidate">
                                <i class='bx bx-check-circle'></i>
                                <div class="candidate-info">
                                    <p class="candidate-name">${candidate}</p>
                                </div>
                            </div>`;
                    });
                    html += '</div>';
                }
                
                html += '</li>';
            }
            html += '</ul>';
            
            document.getElementById('previewContent').innerHTML = html;
            previewModal.show();
        }

        function submitVote() {
            document.getElementById('votingForm').submit();
        }

        function selectCandidate(card, positionId) {
            // Prevent selecting if clicking on platform button
            if (event.target.closest('.btn-platform')) {
                return;
            }
            
            const checkbox = card.querySelector('input[type="checkbox"]');
            const positions = <?php echo json_encode($positions); ?>;
            const maxVotes = positions[positionId]?.max_votes || 1;
            const currentChecked = document.querySelectorAll(`input[name="vote[${positionId}][]"]:checked`).length;
            
            // If trying to select more than max_votes, prevent it
            if (!checkbox.checked && currentChecked >= maxVotes) {
                toastr.warning(`You can only select ${maxVotes} candidate(s) for this position`);
                return;
            }
            
            // Toggle checkbox
            checkbox.checked = !checkbox.checked;
            
            // Toggle selected class
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        }

        // Form validation with AdminLTE toast notifications
        document.getElementById('votingForm').addEventListener('submit', function(event) {
            // Always prevent default submit since we're using our own submission flow
            event.preventDefault();
        });

        // Initialize toastr options
        toastr.options = {
            "closeButton": true,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": true,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
    </script>
</body>
</html>
