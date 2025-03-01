<?php
session_start();
require_once '../config/database.php';

// Check if user is Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Super Admin' && $_SESSION['user_role'] !== 'Sub-Admin') {
    header('Location: dashboard.php');
    exit();
}

// Get all Sub-Admins
$stmt = $pdo->query("
    SELECT id, name, email, created_at 
    FROM users 
    WHERE role = 'Sub-Admin'
    ORDER BY name
");
$subAdmins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sub-Admins - E-VOTE!</title>
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
                    <a href="manage_voters.php" class="nav-link">
                        <i class='bx bxs-group'></i>
                        <span>Manage Voters</span>
                    </a>
                    <a href="manage_admins.php" class="nav-link active">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Sub-Admins</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                        <i class='bx bx-plus'></i> Add New Sub-Admin
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

                <!-- Sub-Admins List -->
                <div class="card">
                    <div class="card-body">
                        <table id="adminsTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subAdmins as $admin): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($admin['name']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="editAdmin(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['name']); ?>', '<?php echo htmlspecialchars($admin['email']); ?>')">
                                                <i class='bx bx-edit'></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteAdmin(<?php echo $admin['id']; ?>)">
                                                <i class='bx bx-trash'></i> Remove
                                            </button>
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

    <!-- Add Sub-Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Sub-Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_admin.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Sub-Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Sub-Admin Modal -->
    <div class="modal fade" id="editAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Sub-Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_admin.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" name="password">
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#adminsTable').DataTable({
                order: [[0, 'asc']]
            });
        });

        function editAdmin(id, name, email) {
            document.getElementById('edit_admin_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            new bootstrap.Modal(document.getElementById('editAdminModal')).show();
        }

        function deleteAdmin(adminId) {
            if (confirm('Are you sure you want to remove this Sub-Admin?')) {
                window.location.href = 'process_admin.php?action=delete&id=' + adminId;
            }
        }
    </script>
</body>
</html>
