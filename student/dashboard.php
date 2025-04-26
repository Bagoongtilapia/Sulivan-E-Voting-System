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

// Get election status and result authentication status
try {
    $stmt = $pdo->query("SELECT status, is_result_authenticated FROM election_status WHERE id = (SELECT MAX(id) FROM election_status)");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $electionStatus = $result['status'] ?? 'Pre-Voting';
    $isResultAuthenticated = (bool)($result['is_result_authenticated'] ?? false);
} catch (PDOException $e) {
    error_log("Error getting election status: " . $e->getMessage());
    $electionStatus = 'Pre-Voting';
    $isResultAuthenticated = false;
}

// Check if user has already voted - consolidated check
try {
    // First check the session variable for immediate feedback after voting
    if (isset($_SESSION['has_voted']) && $_SESSION['has_voted']) {
        $hasVoted = true;
    } else {
        // Check the database for voted status
        $stmt = $pdo->prepare("SELECT voted FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $hasVoted = (bool)$stmt->fetchColumn();
        
        // Update session if user has voted
        if ($hasVoted) {
            $_SESSION['has_voted'] = true;
        }
    }
} catch (PDOException $e) {
    error_log("Error checking voting status: " . $e->getMessage());
    $hasVoted = false;
}

// Clear any existing messages if not in voting phase
if ($electionStatus !== 'Voting') {
    if (isset($_SESSION['message'])) {
        unset($_SESSION['message']);
    }
    if (isset($_SESSION['vote_message'])) {
        unset($_SESSION['vote_message']);
    }
    if (isset($_SESSION['has_voted'])) {
        unset($_SESSION['has_voted']);
    }
}

// Get positions and candidates if in voting phase and user hasn't voted
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

// Consolidated message display function
function displayAlert($type, $message) {
    $messageClass = ($type === 'success') ? 'alert-success' : 
                   (($type === 'info') ? 'alert-info' : 'alert-danger');
    $icon = ($type === 'success') ? 'bx-check-circle' :
            (($type === 'info') ? 'bx-info-circle' : 'bx-error-circle');
    echo '<div class="alert ' . $messageClass . '"><i class="bx ' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

// Display messages using the consolidated function
if(isset($_SESSION['message'])) {
    // Only display session message if user hasn't voted
    if (!$hasVoted) {
        displayAlert($_SESSION['message']['type'], $_SESSION['message']['text']);
    }
    unset($_SESSION['message']);
}

if(isset($_SESSION['vote_message'])) {
    if (!$hasVoted) {
        displayAlert('info', $_SESSION['vote_message']);
    }
    unset($_SESSION['vote_message']);
}

// Check if password needs to be changed
$stmt = $pdo->prepare("SELECT password_changed FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// If password hasn't been changed and it's pre-voting period, redirect to change password
if (!$user['password_changed'] && $electionStatus === 'Pre-Voting') {
    header('Location: change_password.php');
    exit();
}

// Debug information
error_log("Session data: " . print_r($_SESSION, true));

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
            background: linear-gradient(135deg, #393CB2, #5558CD);
            padding: 0.8rem 1.5rem;
            box-shadow: none;
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.8);
            transition: transform 0.3s ease;
        }

        .brand-text {
            font-weight: 600;
            font-size: 40px;
            letter-spacing: 0.5px;
        }

        .navbar-toggler {
            margin-right: 20px;
            padding: 0.5rem;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
            font-size: 25px;
        }

        .user-info {
            
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: 1rem;
        }

        .user-info i {
            color: white;
            font-size: 1.2rem;
        }

        .user-info span {
            color: white;
            font-size: 0.95rem;
        }

        .btn-outline-light {
            border-width: 2px;
            padding: 0.3rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: none;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: #2A2D8F;
                padding: 1rem;
                border-radius: 8px;
                margin-top: 1rem;
            }

            .nav-profile {
                flex-direction: column;
                width: 100%;
                gap: 1rem;
            }

            .user-info {
                width: 100%;
                justify-content: center;
                margin-right: 0;
            }

            .btn-outline-light {
                width: 100%;
                justify-content: center;
                border: none;
                background: rgba(255, 255, 255, 0.1);
                transition: all 0.3s ease;
            }

            .btn-outline-light:hover {
                background: rgba(255, 255, 255, 0.39);
                transform: none;
            }
        }

        @media (max-width: 576px) {
           
            .container {
                padding: 0 1rem;
            }

            .brand-logo {
                width: 60px;
                height: 60px;
                margin-left: 20px;
            }

            .brand-text {
                font-size: 25px;
            }
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
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-light);
            text-align: center;
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
            margin-bottom: 30px;
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
            font-size: 1.05rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }
        .btn-platform:hover {
            background: var(--primary-light);
            color: white;
        }
        .btn-platform i {
            font-size: 0.8rem;
        }
        .candidate-name {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-top: 10px;
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
            border-radius: 15px;
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
            background: var(--primary-color);
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
            font-size: 2rem;
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
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .preview-modal .modal-dialog {
                margin: 1rem;
            }

            .preview-modal .modal-content {
                border-radius: 15px;
            }

            .preview-modal .modal-header {
                padding: 1.25rem;
            }

            .preview-modal .modal-title {
                font-size: 1.2rem;
            }

            .preview-modal .modal-body {
                padding: 1.5rem;
            }

            .preview-notice {
                padding: 1rem;
                font-size: 0.9rem;
            }

            .preview-notice i {
                font-size: 1.5rem;
            }

            .preview-position {
                padding: 1.25rem;
            }

            .preview-position h4 {
                font-size: 1.1rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .preview-position h4 .position-count {
                font-size: 0.8rem;
                padding: 0.3rem 0.6rem;
            }

            .preview-candidate {
                padding: 0.75rem;
            }

            .preview-candidate i {
                font-size: 1.2rem;
                width: 30px;
                height: 30px;
            }

            .preview-candidate .candidate-name {
                font-size: 1rem;
            }

            .preview-buttons {
                margin-top: 1.5rem;
                padding-top: 1.25rem;
            }

            .btn-confirm {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
    <!-- Modern Preview Modal Styles (force override) -->
    <style>
    /* Force new styles for the preview modal */
    .preview-modal-modern .modal-content {
        border-radius: 20px !important;
        border: none !important;
        box-shadow: 0 15px 35px rgba(57, 60, 178, 0.15) !important;
        background: #f8f9ff !important;
    }
    .preview-modal-modern .modal-header {
        background: linear-gradient(90deg, #393CB2 60%, #5558CD 100%) !important;
        color: white !important;
        border-radius: 20px 20px 0 0 !important;
        padding: 1.75rem !important;
        border-bottom: none !important;
        position: relative !important;
        overflow: hidden !important;
    }
    .preview-modal-modern .modal-title {
        font-size: 1.4rem !important;
        font-weight: 600 !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.75rem !important;
        letter-spacing: 0.5px !important;
    }
    .preview-modal-modern .btn-close-white {
        opacity: 0.8 !important;
        transition: all 0.2s ease !important;
    }
    .preview-modal-modern .btn-close-white:hover {
        opacity: 1 !important;
    }
    .preview-modal-modern .modal-body {
        padding: 2rem !important;
    }
    .preview-modal-modern .preview-notice {
        background: linear-gradient(45deg, #fff8e1, #fff3cd) !important;
        color: #856404 !important;
        padding: 1.25rem !important;
        border-radius: 15px !important;
        margin-bottom: 2rem !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.75rem !important;
        box-shadow: 0 3px 10px rgba(133, 100, 4, 0.1) !important;
    }
    .preview-modal-modern .preview-position {
        background: white !important;
        border-radius: 15px !important;
        padding: 1.5rem !important;
        margin-bottom: 1.25rem !important;
        border: none !important;
        position: relative !important;
        overflow: hidden !important;
        box-shadow: 0 3px 15px rgba(57, 60, 178, 0.08) !important;
        transition: all 0.3s ease !important;
    }
    .preview-modal-modern .preview-position:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 5px 20px rgba(57, 60, 178, 0.12) !important;
    }
    .preview-modal-modern .preview-position::before {
            content: '';
            position: absolute;
        top: 0;
            left: 0;
        width: 5px;
        height: 100%;
        background: var(--primary-color);
        border-radius: 3px;
    }
    .preview-modal-modern .preview-position h4 {
        color: var(--primary-color) !important;
        font-size: 1.2rem !important;
        font-weight: 600 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        letter-spacing: 0.3px !important;
    }
    .preview-modal-modern .preview-position h4 .position-count {
        font-size: 0.9rem !important;
        color: #666 !important;
        font-weight: 500 !important;
        background: var(--accent-color) !important;
        padding: 0.4rem 0.8rem !important;
        border-radius: 20px !important;
    }
    .preview-modal-modern .preview-candidates {
        padding-left: 0 !important;
    }
    .preview-modal-modern .preview-candidate {
        display: flex !important;
        align-items: center !important;
        gap: 1rem !important;
        margin-bottom: 0.75rem !important;
        padding: 1rem !important;
        background: var(--accent-color) !important;
        border-radius: 12px !important;
        transition: all 0.3s ease !important;
    }
    .preview-modal-modern .preview-candidate:last-child {
        margin-bottom: 0 !important;
    }
    .preview-modal-modern .preview-candidate:hover {
        transform: translateX(5px) !important;
        background: #e0e1ff !important;
    }
    .preview-modal-modern .preview-candidate i {
        color: var(--primary-color) !important;
        font-size: 1.4rem !important;
        background: white !important;
        width: 35px !important;
        height: 35px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 50% !important;
        box-shadow: 0 2px 8px rgba(57, 60, 178, 0.15) !important;
    }
    .preview-modal-modern .preview-candidate .candidate-info {
        flex-grow: 1 !important;
    }
    .preview-modal-modern .preview-candidate .candidate-name {
        font-weight: 500 !important;
        color: #333 !important;
        margin: 0 !important;
        font-size: 1.1rem !important;
    }
    .preview-modal-modern .preview-buttons {
        display: flex !important;
        justify-content: flex-end !important;
        margin-top: 2rem !important;
        padding-top: 1.5rem !important;
        border-top: 2px solid #eef0ff !important;
    }
    .preview-modal-modern .btn-confirm {
        background: linear-gradient(45deg, #2ecc71, #27ae60) !important;
        color: white !important;
        border: none !important;
        padding: 0.6rem 1.2rem !important;
        border-radius: 8px !important;
        font-weight: 500 !important;
        font-size: 0.95rem !important;
        letter-spacing: 0.3px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        transition: all 0.3s ease !important;
        white-space: nowrap !important;
        box-shadow: 0 2px 6px rgba(46, 204, 113, 0.2) !important;
    }
    .preview-modal-modern .btn-confirm:hover {
        background: linear-gradient(45deg, #27ae60, #219a52) !important;
        transform: translateY(-1px) !important;
        color: white !important;
        box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3) !important;
    }
    .preview-modal-modern .btn-confirm i {
        font-size: 1.1rem !important;
    }
    .preview-modal-modern .preview-candidate.no-selection {
        background: #f8f9fa !important;
        opacity: 0.8 !important;
    }
    .preview-modal-modern .preview-candidate.no-selection i {
        color: #6c757d !important;
        background: #e9ecef !important;
    }
    .preview-modal-modern .preview-candidate.no-selection .candidate-name {
        color: #6c757d !important;
        font-style: italic !important;
    }
    @media (max-width: 768px) {
        .preview-modal-modern .modal-dialog {
            margin: 1rem !important;
        }
        .preview-modal-modern .modal-content {
            border-radius: 15px !important;
        }
        .preview-modal-modern .modal-header {
            padding: 1.25rem !important;
        }
        .preview-modal-modern .modal-title {
            font-size: 1.2rem !important;
        }
        .preview-modal-modern .modal-body {
            padding: 1.5rem !important;
        }
        .preview-modal-modern .preview-notice {
            padding: 1rem !important;
            font-size: 0.9rem !important;
        }
        .preview-modal-modern .preview-notice i {
            font-size: 1.5rem !important;
        }
        .preview-modal-modern .preview-position {
            padding: 1.25rem !important;
        }
        .preview-modal-modern .preview-position h4 {
            font-size: 1.1rem !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 0.5rem !important;
        }
        .preview-modal-modern .preview-position h4 .position-count {
            font-size: 0.8rem !important;
            padding: 0.3rem 0.6rem !important;
        }
        .preview-modal-modern .preview-candidate {
            padding: 0.75rem !important;
        }
        .preview-modal-modern .preview-candidate i {
            font-size: 1.2rem !important;
            width: 30px !important;
            height: 30px !important;
        }
        .preview-modal-modern .preview-candidate .candidate-name {
            font-size: 1rem !important;
        }
        .preview-modal-modern .preview-buttons {
            margin-top: 1.5rem !important;
            padding-top: 1.25rem !important;
        }
        .preview-modal-modern .btn-confirm {
            padding: 0.5rem 1rem !important;
            font-size: 0.9rem !important;
        }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand text-white d-flex align-items-center" href="#">
                <img src="../image/Untitled.jpg" 
                     alt="E-Voting System" 
                     class="brand-logo"
                     onerror="this.src='../uploads/default-logo.png'">
                <span class="brand-text ms-2">E-Voting System</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="nav-profile ms-auto d-flex align-items-center">
                    <div class="user-info">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                    </div>
                    <a href="../auth/logout.php" class="btn btn-outline-light">
                        <span>Sign out</span>
                    </a>
                </div>
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
                            <?php echo $electionStatus; ?>
                        </div>
                    </div>
                </div>

                <?php if ($hasVoted && $electionStatus === 'Voting'): ?>
                    <div class="alert alert-success">
                        <i class='bx bx-check-circle me-2'></i>
                        Thank you for voting! Your vote has been recorded.
                    </div>
                <?php elseif ($electionStatus === 'Ended'): ?>
                    <?php if (!$isResultAuthenticated): ?>
                        <div class="alert alert-info d-flex align-items-center">
                            <i class='bx bx-lock-alt me-2'></i>
                            <div class="d-flex align-items-center">
                                <p class="mb-0">Election results are currently being verified. Please check back once they have been authenticated.</p>
                            </div>
                        </div>
                    <?php else: ?>
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
                    <?php endif; ?>
                <?php elseif ($electionStatus === 'Pre-Voting'): ?>
                    <div class="alert alert-warning">
                        <i class='bx bx-time-five me-2'></i>
                        Voting is currently closed.. Please come back soon to cast your vote.
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
                                                        <button type="button" 
                                                                class="btn btn-platform"
                                                                data-name="<?php echo htmlspecialchars($candidate['name']); ?>"
                                                                data-position="<?php echo htmlspecialchars($position['name']); ?>"
                                                                data-platform="<?php echo htmlspecialchars($candidate['platform']); ?>"
                                                                data-image="<?php echo htmlspecialchars($candidate['image_url']); ?>"
                                                                onclick="showPlatform(this)">
                                                            <i class='fas fa-book'></i>
                                                            Platform
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php if ($position['max_votes'] > 1): ?>
                                                <input type="checkbox" 
                                                       name="vote[<?php echo $position['id']; ?>][]" 
                                                           value="<?php echo $candidate['id']; ?>" 
                                                           class="form-check-input"
                                                           data-max-votes="<?php echo $position['max_votes']; ?>">
                                                <?php else: ?>
                                                    <input type="radio" 
                                                           name="vote[<?php echo $position['id']; ?>]" 
                                                       value="<?php echo $candidate['id']; ?>" 
                                                       class="form-check-input">
                                                <?php endif; ?>
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
    <div class="modal fade platform-modal" id="platformModal" tabindex="-1" role="dialog" aria-labelledby="platformModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="candidate-section">
                        <div class="candidate-image-container">
                            <img id="platformCandidateImage" src="" alt="Candidate" class="candidate-image">
                        </div>
                        <h3 id="candidateName" class="candidate-name"></h3>
                        <div id="imageError" class="error-message">Unable to load image</div>
                    </div>
                    <div class="platform-content">
                        <div class="platform-label">Campaign Platform</div>
                        <p id="platformText" class="platform-text"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade preview-modal preview-modal-modern" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
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
        
        // Function to show platform modal
        function showPlatform(button) {
            try {
                const name = button.getAttribute('data-name');
                const platform = button.getAttribute('data-platform');
                const imageUrl = button.getAttribute('data-image');
                
                // Update modal content
                document.getElementById('candidateName').textContent = name || 'Unknown Candidate';
                document.getElementById('platformText').textContent = platform || '';
                
                // Handle image loading with error state
                const imgElement = document.getElementById('platformCandidateImage');
                const errorElement = document.getElementById('imageError');
                
                imgElement.onerror = function() {
                    this.src = '../uploads/candidates/default.png';
                    this.classList.add('error');
                    errorElement.classList.add('show');
                };
                
                imgElement.onload = function() {
                    this.classList.remove('error');
                    errorElement.classList.remove('show');
                };
                
                // Reset states before loading new image
                imgElement.classList.remove('error');
                errorElement.classList.remove('show');
                imgElement.src = imageUrl;
                
                // Show modal
                platformModal.show();
            } catch (error) {
                console.error('Error showing platform:', error);
                const modalBody = document.querySelector('.platform-modal .modal-body');
                modalBody.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <h4>Unable to Load Information</h4>
                        <p>There was a problem loading the candidate's information. Please try again later.</p>
                    </div>
                `;
                platformModal.show();
            }
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
                let selectedCandidates;
                
                if (position.max_votes === 1) {
                    // For single-choice positions (radio buttons)
                    const selectedRadio = document.querySelector(`input[name="vote[${positionId}]"]:checked`);
                    if (selectedRadio) {
                        const card = selectedRadio.closest('.candidate-card');
                        preview[position.name].candidates = [card.querySelector('.candidate-name').textContent.trim()];
                    }
                } else {
                    // For multiple-choice positions (checkboxes)
                    selectedCandidates = document.querySelectorAll(`input[name="vote[${positionId}][]"]:checked`);
                    
                    // Check maximum votes limit
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
                } else {
                    html += `
                        <div class="preview-candidate no-selection">
                            <i class='bx bx-x-circle'></i>
                            <div class="candidate-info">
                                <p class="candidate-name">No candidate selected</p>
                            </div>
                        </div>`;
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
            
            const input = card.querySelector('input[type="radio"], input[type="checkbox"]');
            const positions = <?php echo json_encode($positions); ?>;
            const position = positions[positionId];
            const maxVotes = position.max_votes;
            
            if (input.type === 'radio') {
                // For single-choice positions (radio buttons)
                // Uncheck all other radio buttons in this position
                document.querySelectorAll(`input[name="vote[${positionId}]"]`).forEach(radio => {
                    radio.closest('.candidate-card').classList.remove('selected');
                });
                // Check the selected radio button
                input.checked = true;
                card.classList.add('selected');
            } else {
                // For multiple-choice positions (checkboxes)
            const currentChecked = document.querySelectorAll(`input[name="vote[${positionId}][]"]:checked`).length;
            
            // If trying to select more than max_votes, prevent it
                if (!input.checked && currentChecked >= maxVotes) {
                    toastr.warning(`You can only select ${maxVotes} candidate(s) for ${position.name}`);
                return;
            }
            
            // Toggle checkbox
                input.checked = !input.checked;
            
            // Toggle selected class
                if (input.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
                }
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

