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

// Get statistics
$stats = [];

// Get candidates count by position
$stmt = $pdo->query("
    SELECT p.position_name, COUNT(c.id) as count
    FROM positions p
    LEFT JOIN candidates c ON p.id = c.position_id
    GROUP BY p.position_name
    ORDER BY p.position_name
");
$candidatesByPosition = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total candidates
$stmt = $pdo->query("SELECT COUNT(*) FROM candidates");
$stats['total_candidates'] = $stmt->fetch(PDO::FETCH_COLUMN);

// Get total voters
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Student'");
$stats['total_voters'] = $stmt->fetch(PDO::FETCH_COLUMN);

// Get votes cast
$stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM votes");
$stats['votes_cast'] = $stmt->fetch(PDO::FETCH_COLUMN);

// Calculate voting percentage
$stats['voting_percentage'] = $stats['total_voters'] > 0 
    ? ($stats['votes_cast'] / $stats['total_voters']) * 100 
    : 0;

// Get recent activity
$stmt = $pdo->query("
    SELECT 
        v.timestamp,
        u.name as voter_name,
        p.position_name,
        c.id as candidate_id
    FROM votes v
    JOIN users u ON v.student_id = u.id
    JOIN candidates c ON v.candidate_id = c.id
    JOIN positions p ON c.position_id = p.id
    ORDER BY v.timestamp DESC
    LIMIT 5
");
$recentVotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - E-VOTE!</title>
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
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .progress {
            height: 10px;
            border-radius: 5px;
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
                    <a href="dashboard.php" class="nav-link active">
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
                    <a href="election_results.php" class="nav-link">
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
                    <h2>Dashboard</h2>
                    <div>
                        <span class="badge bg-<?php echo $electionStatus === 'Voting' ? 'success' : ($electionStatus === 'Ended' ? 'danger' : 'warning'); ?> fs-5">
                            Status: <?php echo $electionStatus; ?>
                        </span>
                    </div>
                </div>

                <!-- Election Status Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Election Control Panel</h5>
                        <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                            <form action="update_election_status.php" method="POST" class="d-inline">
                                <select name="status" class="form-select d-inline-block w-auto me-2">
                                    <option value="Pre-Voting" <?php echo $electionStatus === 'Pre-Voting' ? 'selected' : ''; ?>>Pre-Voting</option>
                                    <option value="Voting" <?php echo $electionStatus === 'Voting' ? 'selected' : ''; ?>>Voting</option>
                                    <option value="Ended" <?php echo $electionStatus === 'Ended' ? 'selected' : ''; ?>>Ended</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Update Status</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Candidates</h6>
                                <p class="card-text display-4"><?php echo $stats['total_candidates']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Registered Voters</h6>
                                <p class="card-text display-4"><?php echo $stats['total_voters']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Votes Cast</h6>
                                <p class="card-text display-4"><?php echo $stats['votes_cast']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Voter Turnout</h6>
                                <p class="card-text display-4"><?php echo number_format($stats['voting_percentage'], 1); ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Candidates by Position -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Candidates by Position</h5>
                                <?php foreach ($candidatesByPosition as $position): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo htmlspecialchars($position['position_name']); ?></span>
                                            <span class="badge bg-primary"><?php echo $position['count']; ?> candidates</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Voting Activity</h5>
                                <?php if ($electionStatus === 'Voting' || $electionStatus === 'Ended'): ?>
                                    <?php foreach ($recentVotes as $vote): ?>
                                        <div class="d-flex align-items-center mb-3">
                                            <i class='bx bxs-check-circle text-success me-2'></i>
                                            <div>
                                                <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($vote['timestamp'])); ?></small>
                                                <div>Vote cast for <?php echo htmlspecialchars($vote['position_name']); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentVotes)): ?>
                                        <p class="text-muted">No votes cast yet</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted">Voting has not started yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
