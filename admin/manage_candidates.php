<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get all candidates with their positions
$stmt = $pdo->query("
    SELECT c.*, p.position_name 
    FROM candidates c
    JOIN positions p ON c.position_id = p.id
    ORDER BY p.position_name, c.name
");
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all positions for the dropdown
$stmt = $pdo->query("SELECT * FROM positions ORDER BY position_name");
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads/candidates/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates - E-VOTE!</title>
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
        .candidate-card {
            transition: transform 0.2s;
        }
        .candidate-card:hover {
            transform: translateY(-5px);
        }
        .candidate-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .platform-text {
            max-height: 100px;
            overflow-y: auto;
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
                    <a href="manage_candidates.php" class="nav-link active">
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
                    <h2>Manage Candidates</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                        <i class='bx bx-plus'></i> Add New Candidate
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Candidates List -->
                <div class="row">
                    <?php foreach ($candidates as $candidate): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card candidate-card">
                                <div class="card-body text-center">
                                    <img src="<?php echo !empty($candidate['image_path']) ? '../' . $candidate['image_path'] : '../uploads/candidates/default.png'; ?>" 
                                         alt="<?php echo htmlspecialchars($candidate['name']); ?>" 
                                         class="candidate-image">
                                    <h5 class="card-title"><?php echo htmlspecialchars($candidate['name']); ?></h5>
                                    <p class="card-text">
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($candidate['position_name']); ?></span>
                                    </p>
                                    <div class="platform-text mb-3">
                                        <small class="text-muted"><?php echo nl2br(htmlspecialchars($candidate['platform'])); ?></small>
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-info" onclick="editCandidate(<?php echo htmlspecialchars(json_encode($candidate)); ?>)">
                                            <i class='bx bx-edit'></i> Edit
                                        </button>
                                        <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteCandidate(<?php echo $candidate['id']; ?>)">
                                                <i class='bx bx-trash'></i> Remove
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Candidate Modal -->
    <div class="modal fade" id="addCandidateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Candidate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_candidate.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Candidate Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="position" class="form-label">Position</label>
                            <select class="form-select" name="position_id" required>
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo $position['id']; ?>">
                                        <?php echo htmlspecialchars($position['position_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Candidate Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*" required>
                        </div>
                        <div class="mb-3">
                            <label for="platform" class="form-label">Platform</label>
                            <textarea class="form-control" name="platform" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Candidate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Candidate Modal -->
    <div class="modal fade" id="editCandidateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Candidate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_candidate.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="candidate_id" id="edit_candidate_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_position" class="form-label">Position</label>
                            <select class="form-select" name="position_id" id="edit_position" required>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo $position['id']; ?>">
                                        <?php echo htmlspecialchars($position['position_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_image" class="form-label">New Image (leave blank to keep current)</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label for="edit_platform" class="form-label">Platform</label>
                            <textarea class="form-control" name="platform" id="edit_platform" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCandidate(candidate) {
            document.getElementById('edit_candidate_id').value = candidate.id;
            document.getElementById('edit_position').value = candidate.position_id;
            document.getElementById('edit_platform').value = candidate.platform;
            new bootstrap.Modal(document.getElementById('editCandidateModal')).show();
        }

        function deleteCandidate(candidateId) {
            if (confirm('Are you sure you want to remove this candidate?')) {
                window.location.href = 'process_candidate.php?action=delete&id=' + candidateId;
            }
        }
    </script>
</body>
</html>
