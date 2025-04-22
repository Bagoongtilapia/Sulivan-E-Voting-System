<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin with proper role names
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get current election status
$stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
$electionStatus = $stmt->fetchColumn();

// Get all voters (students) ordered by ID for first-come-first-serve
$stmt = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM votes v WHERE v.student_id = u.id) as has_voted
    FROM users u 
    WHERE u.role = 'Student'
    ORDER BY u.id
");
$voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Voters - E-VOTE!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        /* Enhanced Table Styles */
        .table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.05);
            margin-bottom: 0;
        }

        .table th {
            background: var(--light-bg);
            color: #666;
            font-weight: 500;
            border-bottom: 2px solid #eee;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            color: #444;
            border-bottom: 1px solid #eee;
        }

        .table tbody tr:hover {
            background-color: var(--light-bg);
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.05);
            background: white;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 1rem 1.25rem;
        }

        .card-title {
            color: var(--primary-color);
            font-weight: 600;
            margin: 0;
        }

        /* Button Styles */
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(57, 60, 178, 0.15);
        }

        /* Status Badge */
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-badge.voted {
            background: #E8FFF1;
            color: #0D9448;
        }

        .status-badge.not-voted {
            background: #FFF5E8;
            color: #B65C12;
        }

        /* Search and Length Control */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
        }

        .dataTables_wrapper .dataTables_length select:focus,
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(85, 88, 205, 0.1);
            outline: none;
        }

        /* DataTables Custom Styling */
        .dataTables_length {
            margin-bottom: 1rem;
        }
        
        .dataTables_length select {
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
            font-size: 0.9rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            min-width: 65px;
        }

        .dataTables_length select:focus {
            border-color: var(--primary-light);
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(85, 88, 205, 0.25);
        }

        .dataTables_length label {
            font-weight: 500;
            color: #666;
        }

        .dataTables_filter input {
            padding: 0.375rem 0.75rem;
            font-size: 0.9rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }

        .dataTables_filter input:focus {
            border-color: var(--primary-light);
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(85, 88, 205, 0.25);
        }

        /* Fix dropdown alignment */
        div.dataTables_length select {
            width: 50px !important;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(57, 60, 178, 0.1);
        }

        .modal-header {
            background: var(--light-bg);
            border-bottom: 1px solid #eee;
            border-radius: 12px 12px 0 0;
            padding: 1.25rem;
        }

        .modal-title {
            color: var(--primary-color);
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-label {
            color: #555;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(85, 88, 205, 0.1);
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .action-btn i {
            font-size: 1.1rem;
            margin-right: 0.25rem;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem 1.25rem;
        }

        .alert i {
            font-size: 1.25rem;
            vertical-align: middle;
        }

        /* Floating Action Button */
        .fab-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 999;
        }

        .fab {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-primary);
            box-shadow: 0 4px 12px rgba(57, 60, 178, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .fab:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(57, 60, 178, 0.25);
        }

        .fab i {
            font-size: 1.75rem;
        }

        .fab::after {
            content: 'Add New Voter';
            position: absolute;
            right: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .fab:hover::after {
            opacity: 1;
            visibility: visible;
            right: calc(100% + 15px);
        }

        @media (max-width: 768px) {
            .fab-container {
                bottom: 1.5rem;
                right: 1.5rem;
            }
            
            .fab::after {
                display: none;
            }
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
                    <a href="manage_positions.php" class="nav-link">
                        <i class='bx bxs-badge'></i>
                        <span>Manage Positions</span>
                    </a>
                    <?php endif; ?>
                    <a href="manage_voters.php" class="nav-link active">
                        <i class='bx bxs-group'></i>
                        <span>Manage Voters</span>
                    </a>
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_admins.php" class="nav-link">
                        <i class='bx bxs-user-account'></i>
                        <span>Manage Admins</span>
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
                <div class="section-header d-flex justify-content-between align-items-center mb-4">
                    <h2 style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; font-size: 24px; color: var(--primary-color); min-width: 200px;">Manage Voters</h2>
                </div>

                <?php if ($electionStatus !== 'Pre-Voting'): ?>
                    <div class="alert alert-info mb-4">
                        <i class='bx bx-info-circle me-2'></i>
                        <?php if ($electionStatus === 'Voting'): ?>
                            Voter management is disabled during the voting phase. Please wait until the pre-voting phase to add or modify voters.
                        <?php else: ?>
                            Voter management is disabled after the election has ended. Please wait until the next pre-voting phase.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class='bx bx-error-circle me-2'></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class='bx bx-check-circle me-2'></i>
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            Voter List
                            <span class="text-muted ms-2" style="font-size: 0.9rem;">
                                (<?php echo count($voters); ?> total)
                            </span>
                        </h5>
                        <?php if ($electionStatus === 'Pre-Voting'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVoterModal" 
                                    data-bs-toggle="tooltip" data-bs-placement="left" 
                                    title="Add a new voter to the system">
                                <i class='bx bx-plus-circle me-2'></i>Add New Voter
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="votersTable">
                                <thead>
                                    <tr>
                                        <th data-bs-toggle="tooltip" title="Voter ID in first-come-first-serve order">ID</th>
                                        <th data-bs-toggle="tooltip" title="Voter's full name">Name</th>
                                        <th data-bs-toggle="tooltip" title="Voter's email address for login">Email</th>
                                        <th data-bs-toggle="tooltip" title="Current voting status">Status</th>
                                        <th data-bs-toggle="tooltip" title="Available actions depend on election phase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($voters as $voter): ?>
                                    <tr>
                                        <td><?php echo $voter['id']; ?></td>
                                        <td><?php echo htmlspecialchars($voter['name']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['email']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $voter['has_voted'] ? 'voted' : 'not-voted'; ?>"
                                                  data-bs-toggle="tooltip" 
                                                  title="<?php echo $voter['has_voted'] ? 'This voter has cast their vote' : 'This voter has not yet voted'; ?>">
                                                <?php echo $voter['has_voted'] ? 'Voted' : 'Not Voted'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($electionStatus === 'Pre-Voting'): ?>
                                            <div class="d-flex gap-2">
                                                <form action="process_voter.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="voter_id" value="<?php echo $voter['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger action-btn" 
                                                            data-bs-toggle="tooltip" 
                                                            title="Remove voter and their voting records">
                                                        <i class='bx bx-trash'></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted" data-bs-toggle="tooltip" title="Voter management is disabled during voting">
                                                <i class='bx bx-lock-alt'></i> Locked
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($electionStatus === 'Pre-Voting'): ?>
    <!-- Floating Action Button -->
    <div class="fab-container">
        <button class="fab" data-bs-toggle="modal" data-bs-target="#addVoterModal" aria-label="Add New Voter">
            <i class='bx bx-plus'></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Add Voter Modal -->
    <div class="modal fade" id="addVoterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Voter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="process_voter.php" method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Voter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Voter Modal -->
    <div class="modal fade" id="editVoterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Voter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="process_voter.php" method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="voter_id" id="edit_voter_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="edit_password" name="password" 
                                       placeholder="Leave blank to keep current password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class='bx bx-show'></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Only fill this if you want to change the password</small>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-save me-2'></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize all tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover'
                });
            });

            // Initialize DataTable with proper ordering
            $('#votersTable').DataTable({
                pageLength: 10,
                language: {
                    search: "",
                    searchPlaceholder: "Search voters...",
                    info: "Showing _START_ to _END_ of _TOTAL_ voters",
                    infoEmpty: "No voters found",
                    emptyTable: "No voters available",
                    paginate: {
                        first: '<i class="bx bx-chevrons-left"></i>',
                        last: '<i class="bx bx-chevrons-right"></i>',
                        next: '<i class="bx bx-chevron-right"></i>',
                        previous: '<i class="bx bx-chevron-left"></i>'
                    }
                },
                columnDefs: [
                    { orderable: false, targets: 4 } // Disable sorting on action column
                ]
            });

            // Password visibility toggle
            $('#togglePassword').click(function() {
                const passwordInput = $('#edit_password');
                const icon = $(this).find('i');
                
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('bx-show').addClass('bx-hide');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('bx-hide').addClass('bx-show');
                }
            });
        });

        function editVoter(id, name, email) {
            document.getElementById('edit_voter_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_password').value = '';
            new bootstrap.Modal(document.getElementById('editVoterModal')).show();
        }
    </script>
</body>
</html>
