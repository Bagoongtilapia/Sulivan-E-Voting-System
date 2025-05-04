<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get election status and name from session or set default
if (!isset($_SESSION['election_name'])) {
    $_SESSION['election_name'] = "SSLG ELECTION 2025";
}

try {
    $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
    $electionStatus = $stmt->fetch(PDO::FETCH_COLUMN) ?? 'Pre-Voting';
} catch (PDOException $e) {
    $error = "Error fetching election status";
    $electionStatus = 'Unknown';
}

// Fetch election name from the database
try {
    $stmt = $pdo->query("SELECT election_name FROM election_status ORDER BY id DESC LIMIT 1");
    $electionName = $stmt->fetchColumn() ?: 'SSLG ELECTION 2025';
} catch (PDOException $e) {
    $electionName = 'SSLG ELECTION 2025';
}

// Get statistics
try {
    $stats = [];

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

    // Get live rankings per position with percentages
    $stmt = $pdo->query("
        WITH PositionVotes AS (
            SELECT 
                p.id,
                SUM(CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END) as total_position_votes
            FROM positions p
            LEFT JOIN candidates c ON p.id = c.position_id
            LEFT JOIN votes v ON c.id = v.candidate_id
            GROUP BY p.id
        ),
        CandidateRanks AS (
            SELECT 
                p.id as position_id,
                c.id as candidate_id,
                COUNT(v.id) as vote_count,
                RANK() OVER (PARTITION BY p.id ORDER BY COUNT(v.id) DESC) as rank
            FROM positions p
            LEFT JOIN candidates c ON p.id = c.position_id
            LEFT JOIN votes v ON c.id = v.candidate_id
            GROUP BY p.id, c.id
        )
        SELECT 
            p.id as position_id,
            p.position_name,
            c.name as candidate_name,
            CASE 
                WHEN pv.total_position_votes > 0 
                THEN ROUND((cr.vote_count * 100.0 / pv.total_position_votes), 1)
                ELSE 0 
            END as vote_percentage,
            cr.rank
        FROM positions p
        LEFT JOIN candidates c ON p.id = c.position_id
        LEFT JOIN CandidateRanks cr ON p.id = cr.position_id AND c.id = cr.candidate_id
        LEFT JOIN PositionVotes pv ON p.id = pv.id
        ORDER BY p.id, c.id
    ");
    $liveRankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Only get candidates count for Super Admin
    if ($_SESSION['user_role'] === 'Super Admin') {
        // Get candidates count by position
        $stmt = $pdo->query("
            SELECT p.position_name, COUNT(c.id) as count
            FROM positions p
            LEFT JOIN candidates c ON p.id = c.position_id
            GROUP BY p.id, p.position_name
            ORDER BY p.id
        ");
        $candidatesByPosition = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total candidates
        $stmt = $pdo->query("SELECT COUNT(*) FROM candidates");
        $stats['total_candidates'] = $stmt->fetch(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $error = "Error fetching statistics";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - E-VOTE!</title>
    <link rel="icon" type="image/x-icon" href="/Sulivan-E-Voting-System/image/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
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
            transform: translateX(5px);
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
            background: var(--light-bg);
            border: 1px solid rgba(57, 60, 178, 0.1);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(57, 60, 178, 0.15);
        }

        .stats-card .card-title {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .stats-card .display-4 {
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 2.5rem;
        }

        .stats-card i {
            color: var(--primary-light);
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }

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

        .btn-update {
            height: 36px;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
            gap: 0.5rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.2);
            cursor: pointer;
        }

        .btn-update:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(57, 60, 178, 0.3);
        }

        .btn-update i {
            font-size: 1.1rem;
        }

        .btn-update:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.2);
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(57, 60, 178, 0.08);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(57, 60, 178, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(57, 60, 178, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, #fff, var(--accent-color));
            border-bottom: 1px solid rgba(57, 60, 178, 0.08);
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--accent-color) 0%, transparent 70%);
            border-radius: 0 12px 0 100%;
            opacity: 0.6;
        }

        .card-header h5 {
            color: var(--primary-color);
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        .card-header h5 i {
            font-size: 1.2rem;
            color: var(--primary-color);
            background: var(--accent-color);
            padding: 6px;
            border-radius: 6px;
        }

        .card-body {
            padding: 1.25rem;
        }

        .position-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .position-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.25rem;
            transition: all 0.2s ease;
            cursor: default;
            border-bottom: 1px solid rgba(57, 60, 178, 0.08);
        }

        .position-item:last-child {
            border-bottom: none;
        }

        .position-item:hover {
            background: var(--accent-color);
            transform: translateX(5px);
        }

        .position-name {
            color: var(--primary-color);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .candidate-count {
            background: white;
            color: var(--primary-color);
            padding: 0.35rem 0.875rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.1);
            border: 1px solid rgba(57, 60, 178, 0.08);
        }

        .table {
            margin: 0;
        }

        .table th {
            background: rgba(57, 60, 178, 0.03);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid rgba(57, 60, 178, 0.08);
        }

        .table td {
            padding: 0.75rem 1.25rem;
            vertical-align: middle;
            color: #444;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(57, 60, 178, 0.08);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table-hover tbody tr {
            transition: all 0.2s ease;
            cursor: default;
        }

        .table-hover tbody tr:hover {
            background: var(--accent-color);
            transform: translateX(5px);
        }

        .table-hover tbody tr:hover .position-badge {
            background: white;
            border-color: rgba(57, 60, 178, 0.1);
            box-shadow: 0 3px 6px rgba(57, 60, 178, 0.15);
        }

        .activity-time {
            color: #666;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .voter-name {
            font-weight: 500;
            color: var(--primary-color);
            font-size: 0.95rem;
        }

        .position-badge {
            display: inline-block;
            padding: 0.35rem 0.875rem;
            background: var(--light-bg);
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.08);
            border: 1px solid rgba(57, 60, 178, 0.08);
            transition: all 0.2s ease;
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
            height: 100%;
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
            gap: 0.5rem;
            height: 100%;
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

        .election-status .status-header h3 {
            margin: 0;
            font-size: 1rem;
            color: #444;
        }

        .election-name-control input {
            border: 1px solid rgba(57, 60, 178, 0.2);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .election-name-control input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(57, 60, 178, 0.1);
        }

        .election-name {
            padding: 0.5rem 1rem;
            background: var(--accent-color);
            border-radius: 6px;
            color: var(--primary-color);
        }

        .section-header {
            background: white;
            padding: 1rem;
            padding-left: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(57, 60, 178, 0.1);
            margin-bottom: 2rem;
            height: 100px;
            display: flex;
            align-items: center;
        }

        .election-info {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
        }

        .status-section {
            min-width: 200px;
            height: 65px;
            display: flex;
            margin-top: 30px;
            align-items: center;
        }

        .progress {
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar {
            background: var(--gradient-primary);
            transition: width 0.3s ease;
        }

        .table td {
            vertical-align: middle;
        }

        .position-rankings .table th {
            border-top: none;
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .position-rankings .table td {
            padding: 0.6rem 0.75rem;
            vertical-align: middle;
        }
        
        .position-rankings h6 {
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="sidebar-brand">
                    <img src="../image/Untitled.jpg" alt="E-VOTE! Logo">
                    <h3>E-VOTE!</h3>
                </div>
                <div class="nav flex-column">
                    <a href="dashboard.php" class="nav-link active">
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
                    <a href="election_results.php" class="nav-link">
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
                <div class="section-header">
                    <h2 style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; font-size: 24px; color: var(--primary-color); min-width: 200px;">Dashboard</h2>
                    <div class="election-info">
                        <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                        <div class="election-name-control">
                            <input type="text" 
                                id="electionName" 
                                class="form-control" 
                                value="<?php echo htmlspecialchars($electionName); ?>" 
                                style="min-width: 250px;">
                        </div>
                        <?php else: ?>
                        <div class="election-name">
                            <h5 class="mb-0"><?php echo htmlspecialchars($electionName); ?></h5>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="status-section">
                        <div class="election-status <?php echo strtolower($electionStatus); ?>">
                            <div class="status-header">
                                <h3>Election Status</h3>
                                <div class="status-indicator <?php echo strtolower($electionStatus); ?>">
                                    <i class="bx <?php 
                                        echo $electionStatus === 'Voting' ? 'bx-check-circle' : 
                                            ($electionStatus === 'Ended' ? 'bx-x-circle' : 'bx-time'); 
                                    ?>"></i>
                                    <?php echo $electionStatus; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class='bx bx-error'></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                <div class="election-control">
                    <h4>
                        <i class='bx bx-slider-alt'></i>
                        Election Control Panel
                    </h4>
                    <form action="update_election_status.php" method="POST">
                        <input type="hidden" name="election_name" value="<?php echo htmlspecialchars($electionName); ?>">
                        <select name="status" class="form-select">
                            <option value="Pre-Voting" <?php echo $electionStatus === 'Pre-Voting' ? 'selected' : ''; ?>>Pre-Voting</option>
                            <option value="Voting" <?php echo $electionStatus === 'Voting' ? 'selected' : ''; ?>>Voting</option>
                            <option value="Ended" <?php echo $electionStatus === 'Ended' ? 'selected' : ''; ?>>Ended</option>
                        </select>
                        <button type="submit" class="btn-update">
                            <i class='bx bx-refresh'></i>
                            Update
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <!-- Statistics Cards -->
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="card-title">
                                    <i class='bx bx-user'></i>
                                    Total Voters
                                </h3>
                                <h2 class="display-4"><?php echo number_format($stats['total_voters']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="card-title">
                                    <i class='bx bx-check-circle'></i>
                                    Votes Cast
                                </h3>
                                <h2 class="display-4"><?php echo number_format($stats['votes_cast']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h3 class="card-title">
                                    <i class='bx bx-pie-chart'></i>
                                    Voter Turnout
                                </h3>
                                <h2 class="display-4"><?php echo number_format($stats['voting_percentage'], 1); ?>%</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php if ($_SESSION['user_role'] === 'Super Admin' && isset($candidatesByPosition)): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Candidates by Position</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Position</th>
                                                <th>Number of Candidates</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($candidatesByPosition as $position): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($position['position_name']); ?></td>
                                                <td><?php echo $position['count']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Live Rankings Section -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Rankings by Position</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $currentPosition = '';
                                $currentPositionId = null;
                                foreach ($liveRankings as $ranking):
                                    if ($currentPositionId !== $ranking['position_id']):
                                        if ($currentPosition !== ''): ?>
                                        </tbody></table></div>
                                        <?php endif;
                                        $currentPosition = $ranking['position_name'];
                                        $currentPositionId = $ranking['position_id'];
                                ?>
                                <div class="position-rankings mb-4">
                                    <h6 class="text-primary mb-3"><?php echo htmlspecialchars($ranking['position_name']); ?></h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th style="width: 15%">Rank</th>
                                                <th>Candidate</th>
                                                <th style="width: 45%">Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="text-center"><?php echo $ranking['rank']; ?></td>
                                        <td><?php echo htmlspecialchars($ranking['candidate_name']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $ranking['vote_percentage']; ?>%" 
                                                         aria-valuenow="<?php echo $ranking['vote_percentage']; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="ms-2 text-muted" style="min-width: 3.5rem; font-size: 0.9rem;">
                                                    <?php echo number_format($ranking['vote_percentage'], 1); ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach;
                                if ($currentPosition !== ''): ?>
                                    </tbody></table></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const electionNameInput = document.getElementById('electionName');
            if (electionNameInput) {
                let timeoutId;
                electionNameInput.addEventListener('input', function() {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        fetch('update_election_name.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'election_name=' + encodeURIComponent(this.value)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.style.borderColor = '#28a745';
                                setTimeout(() => {
                                    this.style.borderColor = '';
                                }, 1000);
                            } else {
                                alert('Failed to update election name: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Failed to update election name');
                        });
                    }, 500);
                });
            }
        });
    </script>
</body>
</html>
