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
    <title>Student Dashboard - E-VOTE!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --background-color: #f8f9fa;
            --card-shadow: 0 8px 24px rgba(149, 157, 165, 0.2);
        }

        body {
            background: linear-gradient(135deg, #f6f8fd 0%, #f1f4f9 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)) !important;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
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
            border-bottom: 2px solid #eef2f7;
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
            transition: all 0.3s ease;
            position: relative;
        }

        .candidate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 1px 6px rgba(0,0,0,0.2);
        }

        .candidate-card.selected {
            border: 2px solid #007bff;
            background-color: rgba(0, 123, 255, 0.05);
        }

        .candidate-card.selected::after {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: -10px;
            right: -10px;
            background: #007bff;
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
            background: #3c8dbc;
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
            background: #367fa9;
            color: white;
        }

        .btn-platform i {
            font-size: 0.9rem;
        }

        .candidate-name {
            color: #2c3e50;
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
            color: #28a745;
        }

        .btn-vote {
            background: #28a745;
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
            background: #218838;
            color: white;
        }

        .voting-section {
            margin-bottom: 2rem;
        }

        .position-title {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f4f6f9;
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
            border: 3px solid #f4f6f9;
        }

        .candidate-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .platform-modal .modal-content {
            background: #f8f9fa;
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .platform-modal .modal-header {
            background: #6c757d;
            color: white;
            border-bottom: 0;
            padding: 15px;
            border-radius: 8px 8px 0 0;
        }

        .platform-modal .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
        }

        .platform-modal .modal-body {
            padding: 20px;
            background: #fff;
            border-radius: 0 0 8px 8px;
        }

        .platform-modal .platform-content {
            color: #495057;
            line-height: 1.6;
            font-size: 1rem;
        }

        #platformCandidateName {
            color: #343a40;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class='bx bx-check-shield me-2'></i>E-VOTE!
            </a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3 text-white">
                    <i class='bx bxs-user-circle me-1'></i>
                    <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>
                <?php if ($electionStatus === 'Ended'): ?>
                <a href="election_results.php" class="btn btn-light btn-sm me-2">
                    <i class='bx bxs-trophy'></i> View Results
                </a>
                <?php endif; ?>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class='bx bxs-log-out'></i> Logout
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
                        <i class='bx bx-ballot me-2'></i>Student Voting Panel
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
                        Voting has not yet started. Please check back later.
                    </div>
                <?php elseif ($electionStatus === 'Ended'): ?>
                    <div class="alert alert-info">
                        <i class='bx bx-info-circle me-2'></i>
                        The election has ended. You can view the results now.
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
                                                <input type="radio" 
                                                       name="vote[<?php echo $position['id']; ?>]" 
                                                       value="<?php echo $candidate['id']; ?>" 
                                                       class="form-check-input" 
                                                       required>
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
    <div class="modal fade" id="platformModal" tabindex="-1" aria-labelledby="platformModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white" id="platformModalLabel">
                        <i class="fas fa-scroll me-2"></i>
                        Candidate Platform
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h4 id="platformCandidateName" class="mb-3"></h4>
                    <div id="platformText" class="platform-content"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
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
            document.getElementById('platformCandidateName').textContent = name;
            document.getElementById('platformText').textContent = platform || 'No platform information available.';
            
            // Show the modal
            platformModal.show();
        }

        function selectCandidate(card, positionId) {
            // Prevent selecting if clicking on platform button
            if (event.target.closest('.btn-platform')) {
                return;
            }
            
            // Remove selected class from all cards in the same position
            document.querySelectorAll(`input[name="vote[${positionId}]"]`)
                .forEach(input => input.closest('.candidate-card').classList.remove('selected'));
            
            // Add selected class to clicked card and check its radio button
            card.classList.add('selected');
            const radio = card.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        }

        // Form validation with AdminLTE toast notifications
        document.getElementById('votingForm')?.addEventListener('submit', function(e) {
            const requiredVotes = document.querySelectorAll('input[type="radio"][required]');
            let allVoted = true;

            requiredVotes.forEach(voteGroup => {
                const name = voteGroup.getAttribute('name');
                const voted = document.querySelector(`input[name="${name}"]:checked`);
                if (!voted) {
                    allVoted = false;
                    const position = voteGroup.closest('.voting-section')
                        .querySelector('.position-title').textContent.trim();
                    
                    // Show toast notification
                    toastr.warning(`Please select a candidate for ${position}`);
                }
            });

            if (!allVoted) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
