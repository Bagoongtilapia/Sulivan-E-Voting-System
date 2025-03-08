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

    // Get election name from session or set default
    if (!isset($_SESSION['election_name'])) {
        $_SESSION['election_name'] = "SSLG ELECTION 2025";
    }
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
            ORDER BY p.id ASC, c.id ASC
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
            border-radius: 12px;
            padding: 2rem 1.5rem;
            height: 180px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .stats-card .card-title {
            color: #666;
            font-size: 1.25rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            white-space: nowrap;
        }

        .stats-card .display-4 {
            color: var(--primary-color);
            font-size: 3.5rem;
            font-weight: 600;
            line-height: 1;
            margin: 0;
            font-family: 'Arial', sans-serif;
        }

        .row.stats-row {
            margin-bottom: 2rem;
        }

        .row.stats-row > div {
            margin-bottom: 0;
        }

        .results-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .position-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            page-break-inside: avoid;
            page-break-after: always;
            margin-bottom: 2rem;
        }

        .position-section:last-child {
            page-break-after: avoid;
        }

        .position-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--accent-color);
        }

        .position-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        .candidates-list {
            padding: 0.5rem 0;
        }

        .candidate-row {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            background: var(--light-bg);
        }

        .candidate-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .candidate-name {
            font-size: 1.2rem;
            font-weight: 500;
            color: #333;
        }

        .vote-count {
            font-size: 1rem;
            color: var(--primary-color);
            font-weight: 500;
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

        /* Election Status Styles */
        .election-status {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .election-status-label {
            color: #333;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #FFF3CD;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-badge i {
            font-size: 1.1rem;
        }

        .status-badge.pre-voting {
            background: #FFF3CD;
            color: #856404;
        }

        .status-badge.voting {
            background: #D4EDDA;
            color: #155724;
        }

        .status-badge.ended {
            background: #F8D7DA;
            color: #721C24;
        }

        /* Election Control Styles */
        .election-control {
            background: linear-gradient(135deg, #fff, var(--accent-color));
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 6px rgba(57, 60, 178, 0.08);
            border: 1px solid rgba(57, 60, 178, 0.08);
            position: relative;
            overflow: hidden;
        }

        .election-control::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--accent-color) 0%, transparent 60%);
            border-radius: 0 12px 0 100%;
            opacity: 0.5;
        }

        .election-control h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        .election-control h4 i {
            font-size: 1.2rem;
            color: var(--primary-color);
            background: var(--accent-color);
            padding: 6px;
            border-radius: 6px;
        }

        .election-control form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            position: relative;
            border: 1px solid rgba(57, 60, 178, 0.08);
        }

        .election-control .form-select {
            min-width: 140px;
            height: 36px;
            padding: 0 2rem 0 0.75rem;
            font-size: 0.9rem;
            border: 1px solid rgba(57, 60, 178, 0.1);
            border-radius: 6px;
            background-position: right 0.5rem center;
            background-color: white;
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .election-control .form-select:hover {
            border-color: var(--primary-color);
        }

        .election-control .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(57, 60, 178, 0.1);
            outline: none;
        }

        /* PDF-specific styles */
        .pdf-header {
            display: none;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .pdf-header h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .pdf-header h3 {
            color: var(--primary-dark);
            font-size: 1.8rem;
            font-weight: 500;
        }

        .pdf-header p {
            font-size: 1rem;
            color: #666;
        }

        @media print {
            .position-section {
                break-inside: avoid;
                break-after: page;
            }

            .position-section:last-child {
                break-after: avoid;
            }

            .candidates-list {
                margin-top: 1rem;
            }

            .pdf-header {
                display: block !important;
            }

            .sidebar, .export-btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
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
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <h2 class="mb-0" style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; font-size: 24px;">Election Results</h2>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="election-status">
                            <span class="election-status-label">Election Status</span>
                            <div class="status-badge <?php echo strtolower($electionStatus); ?>">
                                <i class="bx <?php 
                                    echo $electionStatus === 'Voting' ? 'bx-check-circle' : 
                                        ($electionStatus === 'Ended' ? 'bx-x-circle' : 'bx-time'); 
                                ?>"></i>
                                <?php echo $electionStatus; ?>
                            </div>
                        </div>
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
                <div class="results-section" id="pdf-content">
                    <!-- Election Name for PDF -->
                    <div class="text-center mb-4 pdf-header">
                        <h1 class="mb-2">Election Results</h1>
                        <h3 class="text-primary mb-4"><?php echo htmlspecialchars($_SESSION['election_name']); ?></h3>
                        <p class="text-muted">Generated on: <?php echo date('F j, Y g:i A'); ?></p>
                    </div>
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
                        <div class="row stats-row">
                            <div class="col-md-4 mb-4 mb-md-0">
                                <div class="stats-card">
                                    <h5 class="card-title">Total Voters</h5>
                                    <div class="display-4"><?php echo $totalVoters; ?></div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4 mb-md-0">
                                <div class="stats-card">
                                    <h5 class="card-title">Votes Cast</h5>
                                    <div class="display-4"><?php echo $totalVotesCast; ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <h5 class="card-title">Voter Turnout</h5>
                                    <div class="display-4"><?php echo $totalVoters > 0 ? round(($totalVotesCast / $totalVoters) * 100) : 0; ?>%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Position Results -->
                        <?php foreach ($positions as $position): ?>
                            <div class="position-section mb-5">
                                <div class="position-header">
                                    <h3 class="position-title"><?php echo htmlspecialchars($position['name']); ?></h3>
                                </div>
                                <div class="candidates-list">
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
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generatePDF() {
            const element = document.getElementById('pdf-content');
            const opt = {
                margin: 1,
                filename: '<?php echo preg_replace("/[^a-zA-Z0-9]+/", "_", $_SESSION["election_name"]); ?>_results.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    logging: false
                },
                jsPDF: { 
                    unit: 'in', 
                    format: 'letter', 
                    orientation: 'portrait' 
                },
                pagebreak: { mode: 'avoid-all' }
            };

            // Force show the PDF header
            const pdfHeader = element.querySelector('.pdf-header');
            if (pdfHeader) {
                pdfHeader.style.display = 'block';
            }

            html2pdf().set(opt).from(element).save().then(() => {
                // Hide the PDF header again after generating
                if (pdfHeader) {
                    pdfHeader.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
