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
    <link rel="icon" type="image/x-icon" href="/Sulivan-E-Voting-System/image/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="css/admin-shared.css" rel="stylesheet">
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
            color: rgba(255, 255, 255, 0.8);
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

        /* Status Badge */
        .status-badge {
            padding: 0.35rem 0.8rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Table Styles */
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
        .btn-modal-cancel {
            background: var(--accent-color);
            color: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-modal-cancel:hover {
            background: #d8daff;
            transform: translateY(-1px);
        }

        /* DataTables Styling */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(85, 88, 205, 0.1);
            outline: none;
        }

        /* Fix dropdown alignment */
        div.dataTables_length select {
            min-width: 65px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
            background-color: #fff;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        div.dataTables_length select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(85, 88, 205, 0.1);
            outline: none;
        }

        /* Action Button Styles */
        .action-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.2s ease;
        }

        .action-btn i {
            font-size: 1.1rem;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.15);
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: 1px solid #eee;
            background: white;
        }

        .modal-title {
            color: var(--primary-color);
            font-weight: 600;
        }

        .modal-footer {
            background: white;
            border-top: 1px solid #eee;
        }

        /* Input Group Styles */
        .input-group {
            position: relative;
        }

        .input-group .btn {
            padding: 0.375rem 0.75rem;
            color: #6c757d;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
        }

        .input-group .btn:hover {
            color: #495057;
            background-color: #dde0e3;
            border-color: #c4c8cb;
        }

        .input-group .btn i {
            font-size: 1.1rem;
        }

        /* DataTables Pagination Styling */
        .dataTables_paginate .paginate_button {
            border: 1px solidrgb(0, 128, 255);
            background: white;
            border-radius: 6px;
            color: var(--primary-color) !important;
        }

        .dataTables_paginate .paginate_button:hover {
            background: var(--accent-color) !important;
            border-color: var(--primary-light);
            color: var(--primary-color) !important;
        }

        .dataTables_paginate .paginate_button.current {
            background: var(--primary-color) !important;
            border-color: var(--primary-color);
            color: white !important;
        }

        .dataTables_paginate .paginate_button.disabled {
            color: #6c757d !important;
            border-color: #dee2e6;
            background: #f8f9fa !important;
        }

        .dataTables_paginate .paginate_button.disabled:hover {
            background: #f8f9fa !important;
            border-color: #dee2e6;
        }

        .dataTables_info {
            color: #6c757d;
            padding-top: 0.5rem;
        }

        /* Length Menu Styling */
        .dataTables_length label {
            color: #6c757d;
            font-weight: normal;
        }

        /* Search Box Styling */
        .dataTables_filter label {
            color: #6c757d;
            font-weight: normal;
        }

        /* Utility Classes */
        .w-fit-content {
            width: fit-content;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 0.70rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

         /* DataTables info text styling */
         .dataTables_info {
            color: #6c757d;  /* Gray text color */
            padding-top: 0.5rem;
        }

        /* DataTables length label styling */
        .dataTables_length label {
            font-weight: 500;
            color: #666;
        }

        /* DataTables Pagination Styling */
        .dataTables_paginate .paginate_button {
            border: 1px solidrgb(0, 128, 255);
            background: white;
            border-radius: 6px;
            color: var(--primary-color) !important;
            margin-top: 5px;
            margin-left: 5px;
        }

        .dataTables_paginate .paginate_button:hover {
            background: var(--accent-color) !important;
            border-color: var(--primary-light);
            color: var(--primary-color) !important;
        }

        .dataTables_paginate .paginate_button.current {
            background: var(--primary-color) !important;
            border-color: var(--primary-color);
            color: white !important;
        }

        .dataTables_paginate .paginate_button.disabled {
            color: #6c757d !important;
            border-color: #dee2e6;
            background: #f8f9fa !important;
        }

        .dataTables_paginate .paginate_button.disabled:hover {
            background: #f8f9fa !important;
            border-color: #dee2e6;
        }

        .btn-bulk-delete:hover {
            
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
                    <div class="d-flex align-items-center gap-4">
                        <h2 class="mb-0" style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; font-size: 24px; color:#393CB2">Manage Admins</h2>
                    </div>
                </div>
                <?php
                if (isset($_SESSION['admin_message'])) {
                    $msg = $_SESSION['admin_message'];
                    echo "<div class='alert alert-{$msg['type']} alert-dismissible fade show mb-4' role='alert' style='width: 100%;'>"
                        . htmlspecialchars($msg['text']) .
                        "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
                    unset($_SESSION['admin_message']);
                }
                ?>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class='bx bx-check-circle me-2'></i>
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

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            Admin List
                            <span class="text-muted ms-2" style="font-size: 0.9rem;">
                                (<?php echo count($subAdmins); ?> total)
                            </span>
                        </h5>
                        <div class="d-flex gap-2">
                            <button id="deleteSelected" class="btn btn-danger btn-bulk-delete" style="display: none;">
                                <i class='bx bx-trash me-2'></i>Delete Selected
                            </button>
                            <button class="btn-add-main" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                <i class='bx bx-plus'></i>
                                Add New Admin
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="adminsTable">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subAdmins as $admin): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input admin-checkbox" value="<?php echo $admin['id']; ?>">
                                        </td>
                                        <td><?php echo htmlspecialchars($admin['name']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <form action="process_admin.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger action-btn">
                                                        <i class='bx bx-trash'></i> Delete
                                                    </button>
                                                </form>
                                            </div>
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

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="process_admin.php" method="POST" id="addAdminForm">
                        <div id="addAdminError" class="alert alert-danger d-none"></div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-add-main">Add Admin</button>
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
            // Initialize DataTable
            $('#adminsTable').DataTable({
                pageLength: 10,
                language: {
                    search: "",
                    searchPlaceholder: "Search admins...",
                    info: "Showing _START_ to _END_ of _TOTAL_ admins",
                    infoEmpty: "No admins found",
                    emptyTable: "No admins available",
                    paginate: {
                        first: '<i class="bx bx-chevrons-left"></i>',
                        last: '<i class="bx bx-chevrons-right"></i>',
                        next: '<i class="bx bx-chevron-right"></i>',
                        previous: '<i class="bx bx-chevron-left"></i>'
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [0, -1] } // Disable sorting on checkbox and actions columns
                ],
                order: [[1, 'asc']] // Sort by name column by default
            });

            // Handle Select All checkbox
            $('#selectAll').change(function() {
                $('.admin-checkbox').prop('checked', $(this).is(':checked'));
                updateDeleteButtonVisibility();
            });

            // Handle individual checkboxes
            $(document).on('change', '.admin-checkbox', function() {
                updateDeleteButtonVisibility();
                // Update select all checkbox
                $('#selectAll').prop('checked', $('.admin-checkbox:checked').length === $('.admin-checkbox').length);
            });

            // Function to show/hide delete button
            function updateDeleteButtonVisibility() {
                const checkedCount = $('.admin-checkbox:checked').length;
                $('#deleteSelected').toggle(checkedCount > 0);
            }

            // Handle bulk delete
            $('#deleteSelected').click(function(e) {
                e.preventDefault();
                
                const selectedIds = $('.admin-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedIds.length === 0) {
                    alert('Please select at least one sub-admin to delete.');
                    return;
                }

                // Directly submit the form without confirmation
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_admin.php';
                form.style.display = 'none';

                // Add bulk_delete action
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'bulk_delete';
                form.appendChild(actionInput);

                // Add admin_ids
                const adminIdsInput = document.createElement('input');
                adminIdsInput.type = 'hidden';
                adminIdsInput.name = 'admin_ids';
                adminIdsInput.value = JSON.stringify(selectedIds);
                form.appendChild(adminIdsInput);

                // Append form to body and submit
                document.body.appendChild(form);
                form.submit();
            });

            // Add Admin Form Validation
            $('#addAdminForm').submit(function(e) {
                $('#addAdminError').addClass('d-none').text('');
                let errorMessage = '';
                const name = $('#name').val().trim();
                if (!name) {
                    errorMessage += 'Please enter full name.<br>';
                } else if (!/^[A-Za-z\s]+$/.test(name)) {
                    errorMessage += 'Full name should only contain letters and spaces.<br>';
                }
                if (errorMessage) {
                    $('#addAdminError').removeClass('d-none').html(errorMessage);
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>
