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
    $error = "Error fetching election status";
    $electionStatus = 'Unknown';
}

// Get results by position with error handling
try {
    // First get total voters and votes cast
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student'");
    $totalVoters = $stmt->fetch(PDO::FETCH_COLUMN);

    // Get total votes cast (count distinct students who have voted)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT student_id) 
        FROM votes 
        WHERE student_id IS NOT NULL
    ");
    $totalVotesCast = $stmt->fetch(PDO::FETCH_COLUMN);

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
            c.image_url,
            COALESCE(vc.vote_count, 0) as vote_count,
            $totalVoters as total_voters
        FROM positions p
        LEFT JOIN candidates c ON p.id = c.position_id
        LEFT JOIN VoteCounts vc ON c.id = vc.candidate_id
        ORDER BY p.position_name, vote_count DESC
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize results by position
    $positions = [];
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
} catch (PDOException $e) {
    $error = "Error fetching election results";
    $positions = [];
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
        .card-title {
            color: #343a40;
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 10px;
        }
        .candidate-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .winner {
            background-color: #d4edda;
        }
        .progress {
            height: 20px;
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
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_candidates.php" class="nav-link">
                        <i class='bx bxs-user-detail'></i> Manage Candidates
                    </a>
                    <a href="manage_positions.php" class="nav-link">
                        <i class='bx bxs-badge'></i> Manage Positions
                    </a>
                    <?php endif; ?>
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
                    <div>
                        <span class="badge bg-<?php echo $electionStatus === 'Ended' ? 'success' : 'warning'; ?> fs-5">
                            Status: <?php echo $electionStatus; ?>
                        </span>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Eligible Voters</h5>
                                <h3><?php echo number_format($totalVoters); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Votes Cast</h5>
                                <h3><?php echo number_format($totalVotesCast); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Voter Turnout</h5>
                                <h3><?php 
                                    echo $totalVoters > 0 ? round(($totalVotesCast / $totalVoters) * 100, 1) : 0;
                                ?>%</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Button (Super Admin Only) -->
                <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                <div class="mb-4">
                    <a href="generate_pdf.php" class="btn btn-danger btn-export">
                        <i class='bx bxs-file-pdf'></i> Export as PDF
                    </a>
                </div>
                <?php endif; ?>

                <!-- Results by Position -->
                <div class="row">
                    <?php foreach ($positions as $position): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($position['name']); ?></h5>
                                    
                                    <?php 
                                    // Sort candidates by votes
                                    usort($position['candidates'], function($a, $b) {
                                        return $b['votes'] - $a['votes'];
                                    });
                                    
                                    // Calculate total votes for this position
                                    $positionTotalVotes = array_sum(array_column($position['candidates'], 'votes'));
                                    
                                    foreach ($position['candidates'] as $index => $candidate): 
                                        $percentage = $positionTotalVotes > 0 ? ($candidate['votes'] / $positionTotalVotes) * 100 : 0;
                                        $isWinner = $index === 0 && $candidate['votes'] > 0;
                                    ?>
                                        <div class="candidate-row p-2 mb-2 <?php echo $isWinner ? 'winner' : ''; ?> rounded">
                                            <div class="d-flex align-items-center mb-2">
                                                <?php if ($candidate['image_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($candidate['image_url']); ?>" 
                                                         class="candidate-image me-3" 
                                                         alt="<?php echo htmlspecialchars($candidate['name']); ?>">
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0">
                                                        <?php echo htmlspecialchars($candidate['name']); ?>
                                                        <?php if ($isWinner): ?>
                                                            <i class='bx bxs-crown text-warning'></i>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small><?php echo number_format($candidate['votes']); ?> votes (<?php echo round($percentage, 1); ?>%)</small>
                                                </div>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar <?php echo $isWinner ? 'bg-success' : 'bg-primary'; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $percentage; ?>%" 
                                                     aria-valuenow="<?php echo $percentage; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
