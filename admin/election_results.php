<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get election status
try {
    $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
    $electionStatus = $stmt->fetch(PDO::FETCH_COLUMN) ?? 'Pre-Voting';
} catch (PDOException $e) {
    error_log("Error fetching election status: " . $e->getMessage());
    $electionStatus = 'Unknown';
}

// Initialize variables
$error = null;
$totalVoters = 0;
$totalVotesCast = 0;
$positions = [];

// Get results by position with error handling
try {
    // First get total voters and votes cast
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student'");
    $totalVoters = $stmt->fetch(PDO::FETCH_COLUMN) ?? 0;

    // Get total votes cast (count distinct students who have voted)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT student_id) 
        FROM votes 
        WHERE student_id IS NOT NULL
    ");
    $totalVotesCast = $stmt->fetch(PDO::FETCH_COLUMN) ?? 0;

    // Check if there are any positions first
    $stmt = $pdo->query("SELECT COUNT(*) FROM positions");
    $positionCount = $stmt->fetch(PDO::FETCH_COLUMN) ?? 0;

    if ($positionCount > 0) {
        // Get results by position
        $stmt = $pdo->query("
            WITH VoteCounts AS (
                SELECT 
                    candidate_id,
                    COUNT(*) as vote_count
                FROM votes
                WHERE candidate_id IS NOT NULL
                GROUP BY candidate_id
            )
            SELECT 
                p.id as position_id,
                p.position_name,
                c.id as candidate_id,
                c.name as candidate_name,
                c.image_path as image_url,
                COALESCE(vc.vote_count, 0) as vote_count,
                $totalVoters as total_voters
            FROM positions p
            LEFT JOIN candidates c ON p.id = c.position_id
            LEFT JOIN VoteCounts vc ON c.id = vc.candidate_id
            ORDER BY p.position_name, vote_count DESC
        ");
        
        // Organize results by position
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            if (!isset($positions[$row['position_id']])) {
                $positions[$row['position_id']] = [
                    'name' => $row['position_name'],
                    'candidates' => [],
                    'total_voters' => $row['total_voters']
                ];
            }
            if ($row['candidate_id']) {
                $positions[$row['position_id']]['candidates'][] = [
                    'name' => $row['candidate_name'],
                    'image_url' => $row['image_url'],
                    'votes' => $row['vote_count']
                ];
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching election results: " . $e->getMessage());
    $error = "An error occurred while fetching the election results. Please try again later.";
}

// Get current phase for display
$currentPhase = "";
switch($electionStatus) {
    case 'Pre-Voting':
        $currentPhase = "Election has not started yet. Please add positions and candidates first.";
        break;
    case 'Voting':
        $currentPhase = "Voting is currently in progress.";
        break;
    case 'Ended':
        $currentPhase = "Election has ended.";
        break;
    default:
        $currentPhase = "Unknown status";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - E-VOTE!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            background-color: #343a40;
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .nav-link {
            color: white;
            margin-bottom: 10px;
        }
        .nav-link:hover {
            color: #17a2b8;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
        }
        .card-body {
            padding: 20px;
        }
        .candidate-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .candidate-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .candidate-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .candidate-info {
            margin-left: 15px;
        }
        .progress {
            height: 20px;
            margin-top: 10px;
            background-color: #e9ecef;
            border-radius: 10px;
        }
        .progress-bar {
            background-color: #17a2b8;
            border-radius: 10px;
            transition: width 0.5s ease-in-out;
        }
        .stats-card {
            background: linear-gradient(145deg, #fff, #f8f9fa);
            border-radius: 15px;
            padding: 20px;
            height: 100%;
        }
        .stats-card .card-title {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stats-card .display-6 {
            color: #343a40;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h3 class="mb-4">E-VOTE!</h3>
                <div class="nav flex-column">
                    <a href="dashboard.php" class="nav-link">
                        <i class='bx bxs-dashboard'></i> Dashboard
                    </a>
                    <a href="manage_candidates.php" class="nav-link">
                        <i class='bx bxs-user-detail'></i> Manage Candidates
                    </a>
                    <a href="manage_positions.php" class="nav-link">
                        <i class='bx bxs-badge'></i> Manage Positions
                    </a>
                    <a href="manage_voters.php" class="nav-link">
                        <i class='bx bxs-user-account'></i> Manage Voters
                    </a>
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_admins.php" class="nav-link">
                        <i class='bx bxs-user-check'></i> Manage Sub-Admins
                    </a>
                    <?php endif; ?>
                    <a href="election_results.php" class="nav-link active">
                        <i class='bx bxs-bar-chart-alt-2'></i> Election Results
                    </a>
                    <a href="../auth/logout.php" class="nav-link text-danger mt-5">
                        <i class='bx bxs-log-out'></i> Logout
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Election Results</h2>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-<?php echo $electionStatus === 'Ended' ? 'success' : 'warning'; ?> fs-5 me-2">
                            Status: <?php echo htmlspecialchars($electionStatus); ?>
                        </span>
                        <?php if (!empty($positions)): ?>
                        <button class="btn btn-primary" onclick="generatePDF()">
                            <i class='bx bxs-file-pdf me-1'></i> Export to PDF
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Results Section -->
                <div id="election-results-content">
                    <?php if (empty($positions)): ?>
                        <div class="alert alert-info">
                            <h4 class="alert-heading">No Results Available</h4>
                            <p><?php echo htmlspecialchars($currentPhase); ?></p>
                            <?php if ($electionStatus === 'Pre-Voting'): ?>
                                <hr>
                                <p class="mb-0">To get started:</p>
                                <ul>
                                    <li>First, add positions using the "Manage Positions" menu</li>
                                    <li>Then, add candidates for each position using the "Manage Candidates" menu</li>
                                    <li>Once positions and candidates are set up, you can start the election</li>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <h5 class="card-title">Total Eligible Voters</h5>
                                    <p class="card-text display-6"><?php echo $totalVoters; ?></p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <h5 class="card-title">Total Votes Cast</h5>
                                    <p class="card-text display-6"><?php echo $totalVotesCast; ?></p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <h5 class="card-title">Voter Turnout</h5>
                                    <p class="card-text display-6">
                                        <?php echo $totalVoters > 0 ? round(($totalVotesCast / $totalVoters) * 100) : 0; ?>%
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Results by Position -->
                        <div class="row">
                            <?php foreach ($positions as $position): ?>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($position['name']); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if (empty($position['candidates'])): ?>
                                                <p class="text-muted">No candidates for this position.</p>
                                            <?php else: ?>
                                                <?php foreach ($position['candidates'] as $candidate): ?>
                                                    <div class="candidate-card">
                                                        <div class="d-flex align-items-center">
                                                            <div class="candidate-image-wrapper">
                                                                <?php if ($candidate['image_url']): ?>
                                                                    <img src="../<?php echo htmlspecialchars($candidate['image_url']); ?>" 
                                                                        alt="<?php echo htmlspecialchars($candidate['name']); ?>" 
                                                                        class="candidate-image"
                                                                        onerror="this.onerror=null; this.src='../assets/images/default-avatar.png';">
                                                                <?php else: ?>
                                                                    <div class="candidate-image d-flex align-items-center justify-content-center bg-light">
                                                                        <i class='bx bxs-user' style='font-size: 3rem; color: #6c757d;'></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="candidate-info flex-grow-1">
                                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($candidate['name']); ?></h6>
                                                                <?php 
                                                                $percentage = $position['total_voters'] > 0 
                                                                    ? ($candidate['votes'] / $position['total_voters']) * 100 
                                                                    : 0;
                                                                ?>
                                                                <div class="progress">
                                                                    <div class="progress-bar" role="progressbar" 
                                                                        style="width: <?php echo $percentage; ?>%"
                                                                        aria-valuenow="<?php echo $percentage; ?>" 
                                                                        aria-valuemin="0" 
                                                                        aria-valuemax="100">
                                                                        <?php echo $candidate['votes']; ?> votes (<?php echo round($percentage); ?>%)
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generatePDF() {
            // Get the element
            const element = document.getElementById('election-results-content');
            
            // Configuration for PDF generation
            const opt = {
                margin: [10, 10],
                filename: 'election_results.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    logging: false
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait' 
                }
            };

            // Generate PDF
            html2pdf().set(opt).from(element).save()
                .catch(err => console.error('Error generating PDF:', err));
        }
    </script>
</body>
</html>
