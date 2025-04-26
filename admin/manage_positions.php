<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get all positions ordered by ID (so newer positions appear at the bottom)
$stmt = $pdo->query("SELECT * FROM positions ORDER BY id ASC");
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Positions - E-VOTE!</title>
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
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }

        body {
            background: var(--light-bg);
            min-height: 100vh;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
        }
        .nav-link.active:hover {
            color: var(--primary-color);
            background: white;
            transform: translateX(5px);
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

        /* Main Content Styles */
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .page-header h2 {
            color: black;
            margin: 0;
            font-weight: 600;
        }

        .section-header {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(57, 60, 178, 0.1);
            margin-bottom: 2rem;
        }
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 0.70rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(57, 60, 178, 0.2);
        }

        .position-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
        }

        .position-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(57, 60, 178, 0.1);
        }

        .position-card .card-header {
            background: white;
            border-bottom: none;
            padding: 1.25rem;
        }

        .position-card .card-title {
            color: var(--primary-color);
            font-weight: 600;
            margin: 0;
            font-size: 1.25rem;
        }

        .position-card .card-body {
            padding: 1.25rem;
        }

        .max-votes {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.2);
        }

        .max-votes i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
            vertical-align: middle;
        }

        .btn-group .btn {
            border-radius: 8px;
            margin: 0 0.25rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .btn-edit {
            background-color: var(--accent-color);
            color: #000;
            border: none;
        }

        .btn-edit:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-delete {
            background-color: #ffe5e5;
            color: #dc3545;
            border: none;
        }

        .btn-delete:hover {
            background-color: #c82333;
            color: white;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }

        .modal-header .btn-close {
            color: white;
            filter: invert(1) grayscale(100%) brightness(200%);
        }   

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-label {
            color: var(--primary-dark);
            font-weight: 500;
        }

        .form-control {
            border-radius: 8px;
            padding: 0.625rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.2rem rgba(85, 88, 205, 0.25);
        }

        .modal-footer {
            border-top: none;
            padding: 1rem 1.5rem 1.5rem;
        }

        .alert {
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        .add-position-btn {
            
            color: white;
            border-radius: 6px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            background: var(--gradient-primary);
            border: none;
            padding: 0.70rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .add-position-btn:hover {
            background: var(--primary-light);
            color: white;
            text-decoration: none;
        }

        .add-position-btn i {
            font-size: 16px;
    
        }

        .manage-positions-header {
            color: var(--primary-color);
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
            font-size: 24px;
            margin: 0;
        }

        .btn-modal-cancel {
            background: var(--accent-color);
            color: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-modal-cancel:hover {
            background: #d8daff;
            transform: translateY(-1px);
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
                    <a href="dashboard.php" class="nav-link">
                        <i class='bx bxs-dashboard'></i>
                        <span>Dashboard</span>
                    </a>
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_candidates.php" class="nav-link">
                        <i class='bx bxs-user-detail'></i>
                        <span>Manage Candidates</span>
                    </a>
                    <a href="manage_positions.php" class="nav-link active">
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
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="manage-positions-header">Manage Positions</h2>
                        <button class="add-position-btn" data-bs-toggle="modal" data-bs-target="#addPositionModal">
                            <i class='bx bx-plus'></i>
                            Add New Position
                        </button>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success" role="alert">
                        <i class='bx bx-check-circle me-2'></i><?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class='bx bx-error-circle me-2'></i><?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($positions)): ?>
                    <div class="d-flex flex-column align-items-center justify-content-center" style="min-height: 350px;">
                        <img src="../image/nocandidate.jpg" alt="No Positions" style="max-width: 260px; width: 100%; margin-bottom: 2rem; opacity: 0.8;">
                        <h4 style="color: #888; font-weight: 500;">No positions yet</h4>
                        <p style="color: #aaa;">Add your first position to get started!</p>
                    </div>
                <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($positions as $position): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="position-card card h-100">
                                <div class="card-header">
                                    <h5 class="card-title"><?php echo htmlspecialchars($position['position_name']); ?></h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="max-votes">
                                            <i class='bx bx-check-circle'></i>
                                            Max Votes: <?php echo htmlspecialchars($position['max_votes']); ?>
                                        </div>
                                    </div>
                                    <div class="btn-group w-100">
                                        <button class="btn btn-edit edit-position" data-id="<?php echo $position['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($position['position_name']); ?>"
                                                data-max-votes="<?php echo htmlspecialchars($position['max_votes']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editPositionModal">
                                            <i class='bx bx-edit me-1'></i>Edit
                                        </button>
                                        <button class="btn btn-delete delete-position" data-id="<?php echo $position['id']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#deletePositionModal">
                                            <i class='bx bx-trash me-1'></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Position Modal -->
    <div class="modal fade" id="addPositionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_position.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="position_name" class="form-label">Position Name</label>
                            <input type="text" class="form-control" id="position_name" name="position_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="max_votes" class="form-label">Maximum Votes Allowed</label>
                            <input type="number" class="form-control" id="max_votes" name="max_votes" min="1" value="1" required>
                            <small class="text-muted">Set to 2 or more to allow multiple selections</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="add" class="btn btn-primary">Add Position</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Position Modal -->
    <div class="modal fade" id="editPositionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_position.php" method="POST">
                    <input type="hidden" name="position_id" id="edit_position_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_position_name" class="form-label">Position Name</label>
                            <input type="text" class="form-control" id="edit_position_name" name="position_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_max_votes" class="form-label">Maximum Votes Allowed</label>
                            <input type="number" class="form-control" id="edit_max_votes" name="max_votes" min="1" required>
                            <small class="text-muted">Set to 2 or more to allow multiple selections</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="edit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Position Modal -->
    <div class="modal fade" id="deletePositionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_position.php" method="POST">
                    <input type="hidden" name="position_id" id="delete_position_id">
                    <div class="modal-body">
                        <p>Are you sure you want to delete the position?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="delete" class="btn btn-danger">Delete Position</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Edit position
            $('.edit-position').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const maxVotes = $(this).data('max-votes');
                $('#edit_position_id').val(id);
                $('#edit_position_name').val(name);
                $('#edit_max_votes').val(maxVotes);
                $('#editPositionModal').modal('show');
            });

            // Delete position
            $('.delete-position').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                $('#delete_position_id').val(id);
                $('#delete_position_name').text(name);
                $('#deletePositionModal').modal('show');
            });
        });
    </script>
</body>
</html>
