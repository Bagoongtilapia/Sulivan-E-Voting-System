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

// Check if student has already voted
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() > 0) {
    header('Location: dashboard.php?error=You have already voted');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get all positions
        $stmt = $pdo->query("SELECT id, position_name, max_votes FROM positions ORDER BY id ASC");
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($positions as $position) {
            $position_id = $position['id'];
            $max_votes = $position['max_votes'];
            
            // Get selected candidates for this position
            $selected_candidates = isset($_POST['vote'][$position_id]) ? $_POST['vote'][$position_id] : [];
            
            // Convert to array if single value
            if (!is_array($selected_candidates)) {
                $selected_candidates = [$selected_candidates];
            }

            // Validate number of selections
            if (count($selected_candidates) > $max_votes) {
                throw new Exception("Too many candidates selected for " . $position['position_name']);
            }

            // Record votes for this position
            if (empty($selected_candidates)) {
                // Record a blank vote for this position
                $stmt = $pdo->prepare("INSERT INTO votes (student_id, candidate_id, is_blank_vote) VALUES (?, NULL, 1)");
                $stmt->execute([$_SESSION['user_id']]);
            } else {
                // Record votes for selected candidates
                foreach ($selected_candidates as $candidate_id) {
                    $stmt = $pdo->prepare("INSERT INTO votes (student_id, candidate_id, is_blank_vote) VALUES (?, ?, 0)");
                    $stmt->execute([$_SESSION['user_id'], $candidate_id]);
                }
            }
        }

        // Update the user's voted status
        $updateStmt = $pdo->prepare("UPDATE users SET voted = 1 WHERE id = ?");
        $updateStmt->execute([$_SESSION['user_id']]);

        // Commit transaction
        $pdo->commit();
        
        // Set session variable to indicate the user has voted
        $_SESSION['has_voted'] = true;
        
        header('Location: dashboard.php');
        exit();

    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        header('Location: cast_vote.php?error=' . urlencode($e->getMessage()));
        exit();
    }
}

// Get positions and candidates
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
    ORDER BY p.id ASC, c.name ASC
");

$positions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($positions[$row['position_id']])) {
        $positions[$row['position_id']] = [
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Vote - E-VOTE!</title>
    <link rel="icon" type="image/x-icon" href="/Sulivan-E-Voting-System/image/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --background-color: #f8f9fa;
            --text-color: #2d3748;
            --border-color: #e9ecef;
        }

        body {
            background: linear-gradient(135deg, #f6f8fd 0%, #f1f4f9 100%);
            min-height: 100vh;
            color: var(--text-color);
        }

        .candidate-card {
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 20px;
            height: 100%;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .candidate-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(102, 126, 234, 0.05);
        }

        .candidate-content {
            display: flex;
            flex-direction: row-reverse;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
            flex: 1;
        }

        .candidate-image-container {
            width: 130px;
            height: 130px;
            flex-shrink: 0;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .candidate-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .candidate-details {
            flex-grow: 1;
            text-align: right;
            padding-left: 15px;
        }

        .candidate-name {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .candidate-position {
            color: #718096;
            font-size: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
        }

        .btn-platform {
            width: 100%;
            background: #dc3545;
            border: none;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: auto;
            text-transform: uppercase;
        }

        .btn-platform:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .form-check {
            margin-top: 15px;
            text-align: right;
        }

        .form-check-input {
            width: 20px;
            height: 20px;
            margin-right: 0;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .platform-modal .modal-header {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .platform-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .platform-modal .modal-body {
            padding: 2rem;
            white-space: pre-line;
            line-height: 1.6;
        }

        .new-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ffc107;
            color: #000;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .platform-btn-container {
            position: relative;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <h2 class="text-center mb-4">Cast Your Vote</h2>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="cast_vote.php" method="POST" id="voteForm">
            <?php foreach ($positions as $position_id => $position): ?>
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="position-header">
                        <h4 class="position-title"><?php echo htmlspecialchars($position['name']); ?></h4>
                        <div class="position-subtitle">
                            <?php if ($position['max_votes'] > 1): ?>
                                Select up to <?php echo $position['max_votes']; ?> candidates
                            <?php else: ?>
                                Select 1 candidate
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <?php foreach ($position['candidates'] as $candidate): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="candidate-card">
                                        <div class="candidate-content">
                                            <!-- Candidate Image -->
                                            <div class="candidate-image-container">
                                                <img src="<?php echo htmlspecialchars($candidate['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($candidate['name']); ?>" 
                                                     class="candidate-image"
                                                     onerror="this.src='../uploads/candidates/default.png'">
                                            </div>
                                            <!-- Candidate Info -->
                                            <div class="candidate-details">
                                                <h5 class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></h5>
                                                <p class="candidate-position">
                                                    <span>Candidate for <?php echo htmlspecialchars($position['name']); ?></span>
                                                    <i class='bx bx-badge-check'></i>
                                                </p>
                                                <div class="form-check">
                                                    <?php if ($position['max_votes'] > 1): ?>
                                                        <input type="checkbox" name="vote[<?php echo $position_id; ?>][]" 
                                                               value="<?php echo $candidate['id']; ?>" 
                                                               class="form-check-input"
                                                               data-max-votes="<?php echo $position['max_votes']; ?>">
                                                    <?php else: ?>
                                                        <input type="radio" name="vote[<?php echo $position_id; ?>]" 
                                                               value="<?php echo $candidate['id']; ?>" 
                                                               class="form-check-input">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="platform-btn-container">
                                            <button type="button" class="btn btn-platform"
                                                    onclick="event.stopPropagation(); viewPlatform('<?php echo htmlspecialchars($candidate['name']); ?>', '<?php echo htmlspecialchars($candidate['platform']); ?>')">
                                                <i class='bx bx-notepad'></i>
                                                View Platform
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Submit Button Section -->
            <div class="text-center mt-5">
                <button type="button" class="btn btn-primary btn-lg px-5 py-3" onclick="previewVotes()" 
                        style="background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <i class='bx bx-check-circle me-2'></i>Review and Submit Votes
                </button>
            </div>
        </form>

        <!-- Preview Modal -->
        <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="previewModalLabel">Review Your Votes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="previewContent">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Make Changes</button>
                        <button type="button" class="btn btn-primary" onclick="submitVotes()" id="submitVoteBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="submitSpinner"></span>
                            Confirm and Submit Votes
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Modal -->
        <div class="modal fade platform-modal" id="platformModal" tabindex="-1" aria-labelledby="platformModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="platformModalLabel">Candidate Platform</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h4 id="platformCandidateName" class="mb-3"></h4>
                        <div id="platformText" class="platform-text"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function viewPlatform(name, platform) {
                document.getElementById('platformCandidateName').textContent = name;
                document.getElementById('platformText').textContent = platform;
                new bootstrap.Modal(document.getElementById('platformModal')).show();
            }

            function selectCandidate(card, positionId) {
                // Prevent platform button from triggering candidate selection
                if (event.target.classList.contains('platform-btn')) {
                    return;
                }
                
                const input = card.querySelector('input[type="radio"], input[type="checkbox"]');
                const maxVotes = parseInt(input.dataset.maxVotes);
                
                if (input.type === 'radio') {
                    // For single-choice positions
                    const allCards = document.querySelectorAll(`input[name="${input.name}"]`).forEach(inp => 
                        inp.closest('.candidate-card').classList.remove('selected')
                    );
                    card.classList.add('selected');
                    input.checked = true;
                } else {
                    // For multiple-choice positions
                    const selectedCount = document.querySelectorAll(`input[name="${input.name}"]:checked`).length;
                    
                    if (!input.checked && selectedCount >= maxVotes) {
                        alert(`You can only select up to ${maxVotes} candidates for this position.`);
                        return;
                    }
                    
                    card.classList.toggle('selected');
                    input.checked = !input.checked;
                }
                
                // Update the select button text
                const selectBtn = card.querySelector('.btn-select');
                if (input.checked) {
                    selectBtn.innerHTML = '<i class="bx bx-check-circle me-1"></i>Selected';
                    selectBtn.classList.add('btn-success');
                } else {
                    selectBtn.innerHTML = '<i class="bx bx-check-circle me-1"></i>Select Candidate';
                    selectBtn.classList.remove('btn-success');
                }
            }

            function previewVotes() {
                const selectedCandidates = [];
                const positions = {};
                
                // Collect all selected candidates
                document.querySelectorAll('.position-vote:checked').forEach(input => {
                    const card = input.closest('.candidate-card');
                    const candidateName = card.querySelector('.candidate-name').textContent;
                    const positionName = card.querySelector('.candidate-position').textContent;
                    
                    if (!positions[positionName]) {
                        positions[positionName] = [];
                    }
                    positions[positionName].push(candidateName);
                });
                
                // Build preview HTML
                let previewHtml = '<div class="preview-content">';
                for (const [position, candidates] of Object.entries(positions)) {
                    previewHtml += `
                        <div class="position-preview mb-3">
                            <h5 class="text-primary mb-2">${position}</h5>
                            <ul class="list-unstyled">
                                ${candidates.map(candidate => `
                                    <li class="mb-2">
                                        <i class="bx bx-check-circle text-success me-2"></i>
                                        ${candidate}
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                }
                previewHtml += '</div>';
                
                // Show preview modal
                const modal = new bootstrap.Modal(document.getElementById('previewModal'));
                document.getElementById('previewContent').innerHTML = previewHtml;
                modal.show();
            }

            function submitVotes() {
                const form = document.getElementById('voteForm');
                const submitBtn = document.getElementById('submitVoteBtn');
                
                // Disable submit button and show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
                
                // Submit the form
                form.submit();
            }
        </script>
    </body>
</html>
