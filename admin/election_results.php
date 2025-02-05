<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get election status
$stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
$electionStatus = $stmt->fetch(PDO::FETCH_COLUMN) ?? 'Pre-Voting';

// Get results by position
$stmt = $pdo->query("
    SELECT 
        p.id as position_id,
        p.position_name,
        c.id as candidate_id,
        u.name as candidate_name,
        COUNT(v.id) as vote_count,
        (SELECT COUNT(*) FROM users WHERE role = 'Student') as total_voters
    FROM positions p
    LEFT JOIN candidates c ON p.id = c.position_id
    LEFT JOIN users u ON c.student_id = u.id
    LEFT JOIN votes v ON c.id = v.candidate_id
    GROUP BY p.id, p.position_name, c.id, u.name
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
            'votes' => $row['vote_count']
        ];
    }
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .progress {
            height: 25px;
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
                    <div>
                        <span class="badge bg-<?php echo $electionStatus === 'Ended' ? 'success' : 'warning'; ?> fs-5">
                            Status: <?php echo $electionStatus; ?>
                        </span>
                    </div>
                </div>

                <?php if ($electionStatus !== 'Ended' && $_SESSION['user_role'] === 'Super Admin'): ?>
                    <div class="alert alert-warning">
                        <i class='bx bx-info-circle'></i>
                        Note: Results are preliminary until the election is marked as ended.
                    </div>
                <?php endif; ?>

                <!-- Results by Position -->
                <div class="row">
                    <?php foreach ($positions as $position): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($position['name']); ?></h5>
                                    <div class="position-results">
                                        <?php 
                                        foreach ($position['candidates'] as $candidate): 
                                            $percentage = $position['total_voters'] > 0 
                                                ? ($candidate['votes'] / $position['total_voters']) * 100 
                                                : 0;
                                        ?>
                                            <div class="candidate-result mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span><?php echo htmlspecialchars($candidate['name']); ?></span>
                                                    <span><?php echo $candidate['votes']; ?> votes (<?php echo number_format($percentage, 1); ?>%)</span>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%"
                                                         aria-valuenow="<?php echo $percentage; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($position['candidates'])): ?>
                                            <p class="text-muted">No candidates for this position</p>
                                        <?php endif; ?>

                                        <div class="mt-3">
                                            <small class="text-muted">
                                                Total Voters: <?php echo $position['total_voters']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($electionStatus === 'Ended'): ?>
                    <div class="card mt-4">
                        <div class="card-body">
                            <h5 class="card-title">Download Results</h5>
                            <p class="card-text">Export the complete election results as a PDF or Excel file.</p>
                            <a href="export_results.php?format=pdf" class="btn btn-primary me-2">
                                <i class='bx bxs-file-pdf'></i> Export as PDF
                            </a>
                            <a href="export_results.php?format=excel" class="btn btn-success">
                                <i class='bx bxs-file-export'></i> Export as Excel
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
