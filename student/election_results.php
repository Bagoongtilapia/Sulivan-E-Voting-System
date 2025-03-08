<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header('Location: ../index.php');
    exit();
}

// Get election status and authentication status
try {
    $stmt = $pdo->query("SELECT status, is_result_authenticated FROM election_status ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $electionStatus = $result['status'] ?? 'Pre-Voting';
    $isResultAuthenticated = $result['is_result_authenticated'] ?? false;
} catch (PDOException $e) {
    $error = "Error fetching election status";
    $electionStatus = 'Unknown';
    $isResultAuthenticated = false;
}

// Only show results if election has ended AND results are authenticated
if ($electionStatus === 'Ended' && $isResultAuthenticated) {
    try {
        // Get winners for each position
        $stmt = $pdo->query("
            WITH VoteCounts AS (
                SELECT 
                    p.id as position_id,
                    p.position_name,
                    c.id as candidate_id,
                    c.name as candidate_name,
                    c.image_url,
                    COUNT(v.id) as vote_count,
                    ROW_NUMBER() OVER (PARTITION BY p.id ORDER BY COUNT(v.id) DESC, c.id ASC) as rank
                FROM positions p
                LEFT JOIN candidates c ON p.id = c.position_id
                LEFT JOIN votes v ON c.id = v.candidate_id
                GROUP BY p.id, p.position_name, c.id, c.name, c.image_url
                ORDER BY p.id
            )
            SELECT 
                v.position_id,
                v.position_name,
                v.candidate_name,
                v.image_url,
                v.vote_count,
                v.rank,
                (SELECT COUNT(*) FROM users WHERE role = 'Student') as total_voters
            FROM VoteCounts v
            WHERE v.rank = 1
            ORDER BY v.position_id
        ");
        $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching election results";
        $winners = [];
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-gradient: linear-gradient(45deg, #00c853, #64dd17);
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

        .results-header {
            text-align: center;
            margin-bottom: 40px;
            color: #2d3748;
        }

        .results-header h1 {
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .winner-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .winner-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(149, 157, 165, 0.3);
        }

        .winner-banner {
            background: var(--success-gradient);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .winner-content {
            padding: 30px;
            text-align: center;
        }

        .winner-badge {
            position: absolute;
            top: 60px;
            right: 20px;
            background: #ffd700;
            color: #2d3748;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .candidate-image {
            width: 150px;
            height: 150px;
            margin: 0 auto 25px;
            position: relative;
        }

        .candidate-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .candidate-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .candidate-placeholder i {
            font-size: 48px;
            color: white;
        }

        .candidate-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .position-name {
            color: #718096;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .vote-stats {
            background: #f8fafc;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }

        .vote-count {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .vote-percentage {
            font-size: 1.1rem;
            color: #718096;
        }

        .progress {
            height: 10px;
            border-radius: 5px;
            margin-top: 15px;
            background-color: #e2e8f0;
        }

        .progress-bar {
            background: var(--success-gradient);
            border-radius: 5px;
            transition: width 1.5s ease-in-out;
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .alert i {
            font-size: 24px;
            margin-right: 15px;
        }

        .alert-info {
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: var(--primary-color);
        }

        .alert-info i {
            color: var(--primary-color);
        }

        .alert-danger {
            background: linear-gradient(45deg, rgba(255, 99, 99, 0.1), rgba(255, 155, 155, 0.1));
            color: #dc3545;
        }

        .alert-danger i {
            color: #dc3545;
        }

        .btn-back {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 10px 20px;
            color: white;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }

        .animate-winner {
            animation: fadeInUp 0.5s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .position-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 0.5rem;
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
                <a href="dashboard.php" class="btn btn-light btn-sm me-2">
                    <i class='bx bxs-dashboard me-1'></i>Dashboard
                </a>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class='bx bxs-log-out'></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class='bx bx-error-circle me-2'></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <?php if ($electionStatus !== 'Ended'): ?>
                <div class="alert alert-info">
                    <i class='bx bx-time-five me-2'></i>
                    Election results are not available yet. Please check back after the election has ended.
                </div>
            <?php elseif (!$isResultAuthenticated): ?>
                <div class="alert alert-info">
                    <i class='bx bx-lock-alt me-2'></i>
                    Election results are currently being verified. Please check back once they have been authenticated.
                </div>
            <?php else: ?>
                <div class="results-header animate-winner">
                    <h1><i class='bx bxs-trophy me-2'></i>Election Results</h1>
                    <p class="text-muted">Congratulations to all the winners!</p>
                </div>

                <div class="row">
                    <?php 
                    $currentPosition = null;
                    $loop = 0;
                    foreach ($winners as $candidate): 
                        if ($currentPosition !== $candidate['position_id']) {
                            if ($currentPosition !== null) {
                                echo '</div>'; // Close previous position row
                            }
                            $currentPosition = $candidate['position_id'];
                            echo '<div class="col-12"><h3 class="position-title mt-4">' . htmlspecialchars($candidate['position_name']) . '</h3></div>';
                            echo '<div class="row">'; // Start new position row
                        }
                    ?>
                        <div class="col-md-6 animate-winner" style="animation-delay: <?php echo $loop * 0.2; ?>s">
                            <div class="winner-card">
                                <?php if ($candidate['rank'] === 1): ?>
                                <div class="winner-banner">
                                    <i class='bx bxs-crown me-2'></i>Winner
                                </div>
                                <div class="winner-badge">
                                    <i class='bx bxs-star'></i>
                                </div>
                                <?php endif; ?>
                                <div class="winner-content">
                                    <div class="candidate-image">
                                        <?php if ($candidate['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($candidate['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($candidate['candidate_name']); ?>" 
                                                 class="candidate-img">
                                        <?php else: ?>
                                            <div class="candidate-placeholder">
                                                <i class='bx bxs-user'></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="candidate-name">
                                        <?php echo htmlspecialchars($candidate['candidate_name']); ?>
                                    </h3>
                                    <div class="position-name">
                                        <i class='bx bxs-badge-check me-1'></i>
                                        <?php echo htmlspecialchars($candidate['position_name']); ?>
                                    </div>
                                    <div class="vote-stats">
                                        <div class="vote-count">
                                            <?php echo number_format($candidate['vote_count']); ?>
                                        </div>
                                        <div class="vote-percentage">
                                            <?php 
                                                $percentage = $candidate['total_voters'] > 0 
                                                    ? round(($candidate['vote_count'] / $candidate['total_voters']) * 100, 1)
                                                    : 0;
                                                echo $percentage . '% of total votes';
                                            ?>
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
                                </div>
                            </div>
                        </div>
                    <?php 
                    $loop++;
                    endforeach; 
                    if ($currentPosition !== null) {
                        echo '</div>'; // Close last position row
                    }
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add animation to progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>
