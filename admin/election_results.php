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
        :root {
            --primary-color: #393CB2;
            --primary-light: #5558CD;
            --primary-dark: #2A2D8F;
            --accent-color: #E8E9FF;
            --gradient-primary: linear-gradient(135deg, #393CB2, #5558CD);
            --light-bg: #F8F9FF;
        }

        body {
            background: var(--light-bg);
            min-height: 100vh;
        }

        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            background: var(--primary-color);
            color: white;
            box-shadow: 4px 0 10px rgba(57, 60, 178, 0.1);
            z-index: 1000;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: var(--light-bg);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            background: var(--primary-color);
        }

        .sidebar-brand img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 100px;
            margin-right: 12px;
        }

        .sidebar-brand h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            white-space: nowrap;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }

        .nav-link.active i {
            color: var(--primary-color);
        }

        .nav-link i {
            margin-right: 12px;
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .nav-link span {
            font-size: 0.95rem;
        }

        .nav-link:not(.active):hover i {
            transform: scale(1.1);
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        .stats-card h4 {
            color: var(--primary-dark);
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .stats-card .display-4 {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .results-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .position-card {
            background: var(--light-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .position-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        .position-title {
            color: var(--primary-color);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent-color);
        }

        .candidate-row {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .candidate-row:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .candidate-info {
            flex: 1;
            margin-right: 1rem;
        }

        .candidate-name {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.25rem;
        }

        .vote-count {
            color: var(--primary-light);
            font-size: 0.9rem;
        }

        .progress {
            height: 8px;
            margin-top: 0.5rem;
            background: var(--accent-color);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            background: var(--gradient-primary);
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        .export-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .export-btn i {
            font-size: 1.25rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-auto sidebar">
                <div class="sidebar-brand">
                    <img src="../image/Untitled.jpg" alt="E-VOTE! Logo">
                    <h3>E-VOTE!</h3>
                </div>
                <div class="nav flex-column">
                    <a href="dashboard.php" class="nav-link">
                        <i class='bx bxs-dashboard'></i>
                        <span>Dashboard</span>
                    </a>
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_candidates.php" class="nav-link">
                        <i class='bx bxs-user-detail'></i>
                        <span>Manage Candidates</span>
                    </a>
                    <a href="manage_positions.php" class="nav-link">
                        <i class='bx bxs-badge'></i>
                        <span>Manage Positions</span>
                    </a>
                    <a href="manage_voters.php" class="nav-link">
                        <i class='bx bxs-group'></i>
                        <span>Manage Voters</span>
                    </a>
                    <a href="manage_admins.php" class="nav-link">
                        <i class='bx bxs-user-account'></i>
                        <span>Manage Admins</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($_SESSION['user_role'] === 'Sub-Admin'): ?>
                    <a href="manage_voters.php" class="nav-link">
                        <i class='bx bxs-group'></i>
                        <span>Manage Voters</span>
                    </a>
                    <?php endif; ?>
                    <a href="election_results.php" class="nav-link active">
                        <i class='bx bxs-bar-chart-alt-2'></i>
                        <span>Election Results</span>
                    </a>
                    <a href="../auth/logout.php" class="nav-link">
                        <i class='bx bxs-log-out'></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Election Results</h2>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-<?php echo $electionStatus === 'Ended' ? 'success' : 'warning'; ?> fs-5">
                            Status: <?php echo htmlspecialchars($electionStatus); ?>
                        </span>
                        <?php if (!empty($positions)): ?>
                        <button class="export-btn" onclick="generatePDF()">
                            <i class='bx bxs-file-pdf'></i>
                            Export Results
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Results Section -->
                <div class="results-section">
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
                                    <h4>Total Voters</h4>
                                    <div class="display-4"><?php echo $totalVoters; ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <h4>Total Votes Cast</h4>
                                    <div class="display-4"><?php echo $totalVotesCast; ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <h4>Voter Turnout</h4>
                                    <div class="display-4">
                                        <?php echo $totalVoters > 0 ? round(($totalVotesCast / $totalVoters) * 100) : 0; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Results by Position -->
                        <div class="row">
                            <?php foreach ($positions as $position): ?>
                                <div class="col-md-6">
                                    <div class="position-card">
                                        <h3 class="position-title"><?php echo htmlspecialchars($position['name']); ?></h3>
                                        <?php if (empty($position['candidates'])): ?>
                                            <p class="text-muted">No candidates for this position.</p>
                                        <?php else: ?>
                                            <?php foreach ($position['candidates'] as $candidate): ?>
                                                <div class="candidate-row">
                                                    <div class="candidate-info">
                                                        <div class="candidate-name">
                                                            <?php echo htmlspecialchars($candidate['name']); ?>
                                                        </div>
                                                        <div class="vote-count">
                                                            <?php echo $candidate['votes']; ?> votes
                                                            (<?php echo $position['total_voters'] > 0 ? round(($candidate['votes'] / $position['total_voters']) * 100) : 0; ?>%)
                                                        </div>
                                                        <div class="progress">
                                                            <div class="progress-bar" role="progressbar" 
                                                                 style="width: <?php echo $position['total_voters'] > 0 ? ($candidate['votes'] / $position['total_voters']) * 100 : 0; ?>%" 
                                                                 aria-valuenow="<?php echo $position['total_voters'] > 0 ? ($candidate['votes'] / $position['total_voters']) * 100 : 0; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
            const element = document.querySelector('.results-section');
            
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
