<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    error_log("User not logged in or not a student. Session: " . print_r($_SESSION, true));
    header('Location: ../index.php');
    exit();
}

// Check if password needs to be changed
$stmt = $pdo->prepare("SELECT password_changed FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check election status
$stmt = $pdo->prepare("SELECT status FROM election_status WHERE id = 1");
$stmt->execute();
$election = $stmt->fetch();

// If password hasn't been changed and it's pre-voting period, redirect to change password
if (!$user['password_changed'] && $election['status'] === 'Pre-Voting') {
    header('Location: change_password.php');
    exit();
}

// Debug information
error_log("Session data: " . print_r($_SESSION, true));

// Get election status
try {
    $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
    $electionStatus = $stmt->fetch(PDO::FETCH_COLUMN) ?? 'Pre-Voting';
    error_log("Election status: " . $electionStatus);
} catch (PDOException $e) {
    error_log("Error fetching election status: " . $e->getMessage());
    $error = "Error fetching election status";
    $electionStatus = 'Unknown';
}

// Get student's voting status
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE student_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $hasVoted = $stmt->fetchColumn() > 0;
    error_log("Has voted status for student {$_SESSION['user_id']}: " . ($hasVoted ? 'Yes' : 'No'));
} catch (PDOException $e) {
    error_log("Error checking voting status: " . $e->getMessage());
    $error = "Error checking voting status";
    $hasVoted = false;
}

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
            ORDER BY p.position_name, c.name
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

// Check if form is submitted
if(isset($_POST['vote'])) {
    $votes = $_POST['vote'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Insert each vote
        foreach($votes as $position_id => $candidate_ids) {
            foreach($candidate_ids as $candidate_id) {
                $sql = "INSERT INTO votes (student_id, candidate_id, position_id) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$student, $candidate_id, $position_id]);
            }
        }
        
        // Update voters status
        $sql = "UPDATE users SET voted = 1 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        // Unset session and redirect
        unset($_SESSION['user_id']);
        header('location: ../index.php');
        exit();
        
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header('location: dashboard.php');
        exit();
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

        .modal-header .close {
            color: white;
            opacity: 0.8;
            text-shadow: none;
            margin: -1rem -1rem -1rem auto;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
            background-color: white;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="card-title mb-0">
                        <i class='bx bx-ballot me-2'></i>
                    </h2>
                    <span class="badge status-badge bg-<?php 
                        echo $electionStatus === 'Voting' ? 'success' : 
                            ($electionStatus === 'Ended' ? 'danger' : 'warning'); 
                    ?>">
                        <i class='bx bx-radio-circle-marked me-1'></i>
                        Status: <?php echo htmlspecialchars($electionStatus); ?>
                    </span>
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
                            // Get positions with winners and vote counts
                            $sql = "SELECT 
                                p.id, 
                                p.position_name, 
                                p.max_votes,
                                c.id as candidate_id, 
                                c.name as candidate_name, 
                                c.image_path, 
                                c.platform,
                                (SELECT COUNT(*) 
                                 FROM votes v 
                                 WHERE v.candidate_id = c.id) as vote_count
                            FROM positions p
                            LEFT JOIN candidates c ON p.id = c.position_id
                            ORDER BY p.position_name, vote_count DESC";
                            
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
                                        'name' => $row['candidate_name'],
                                        'image_path' => $row['image_path'] ? '../' . $row['image_path'] : '../uploads/candidates/default.png',
                                        'platform' => $row['platform'],
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
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary view-platform" 
                                                        data-name="<?php echo htmlspecialchars($candidate['name']); ?>"
                                                        data-platform="<?php echo htmlspecialchars($candidate['platform']); ?>"
                                                        onclick="viewPlatform(this)">
                                                    <i class="fas fa-book"></i> View Platform
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach;
                            
                        } catch(PDOException $e) {
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
                    <form action="cast_vote.php" method="POST" id="votingForm">
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
                            <button type="submit" class="btn btn-vote">
                                <i class='bx bx-check-circle me-2'></i>Submit Your Vote
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
                <div class="modal-header">
                    <h4 class="modal-title" id="platformModalLabel">
                        <i class="bx bx-book-open mr-2"></i>
                        <span class="candidate-name"></span>
                    </h4>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="candidate-platform-header">
                        Platform and Goals
                    </div>
                    <div class="platform-content" id="platformContent">
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
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
        
        function viewPlatform(button) {
            // Prevent the card selection
            event.stopPropagation();
            
            // Get data from button attributes
            const name = button.getAttribute('data-name');
            const platform = button.getAttribute('data-platform');
            
            // Update modal content
            document.querySelector('.modal-title .candidate-name').textContent = name;
            document.getElementById('platformContent').textContent = platform || 'No platform information available.';
            
            // Show the modal
            platformModal.show();
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
            event.preventDefault();
            let isValid = true;
            const positions = <?php echo json_encode($positions); ?>;

            // Check each position
            for (const positionId in positions) {
                const position = positions[positionId];
                const checkedCount = document.querySelectorAll(`input[name="vote[${positionId}][]"]:checked`).length;
                const maxVotes = position.max_votes || 1;

                if (checkedCount === 0) {
                    isValid = false;
                    toastr.warning(`Please select at least one candidate for ${position.name}`);
                } else if (checkedCount > maxVotes) {
                    isValid = false;
                    toastr.warning(`You can only select ${maxVotes} candidate(s) for ${position.name}`);
                }
            }

            if (isValid) {
                // Remove the event.preventDefault() effect and submit the form
                event.target.submit();
            }
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
