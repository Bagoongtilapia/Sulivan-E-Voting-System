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
    ORDER BY p.id ASC, c.id ASC
");
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all positions for the dropdown
$stmt = $pdo->query("SELECT * FROM positions ORDER BY id ASC");
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

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: var(--light-bg);
        }

        .candidate-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(57, 60, 178, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            padding: 1.5rem;
            text-align: center;
        }

        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(57, 60, 178, 0.2);
        }

        .candidate-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 3px solid var(--accent-color);
            padding: 3px;
        }

        .candidate-name {
            color: var(--primary-color);
            font-size: 1.25rem;
            font-weight: 600;
            margin: 1rem 0 0.5rem;
        }

        .position-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .platform-text {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 1rem 0;
            height: 100px;
            overflow-y: auto;
            padding: 0.5rem;
            background: var(--light-bg);
            border-radius: 8px;
        }

        .action-buttons {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-edit {
            background: var(--accent-color);
            color: var(--primary-color);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-edit:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-remove {
            background: #ffe5e5;
            color: #dc3545;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-remove:hover {
            background: #dc3545;
            color: white;
        }

        .add-candidate-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .add-candidate-btn:hover {
            background: var(--primary-light);
            color: white;
            text-decoration: none;
        }

        .add-candidate-btn i {
            font-size: 16px;
        }

        .section-header {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(57, 60, 178, 0.1);
            margin-bottom: 2rem;
        }

        /* Add animation for alerts */
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Modal Styling */
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(57, 60, 178, 0.15);
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-bottom: none;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-title i {
            font-size: 1.5rem;
        }

        .modal-body {
            padding: 2rem 1.5rem;
            background: var(--light-bg);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--accent-color);
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(57, 60, 178, 0.15);
        }

        .form-control::placeholder {
            color: #aaa;
        }

        .image-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid var(--accent-color);
            padding: 3px;
            margin: 0 auto;
            display: block;
            object-fit: cover;
            background: white;
        }

        .image-preview-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 1rem auto;
        }

        .image-preview-wrapper i {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3.5rem;
            color: var(--primary-color);
            opacity: 0.6;
            transition: all 0.3s ease;
        }

        .image-preview-text {
            text-align: center;
            color: var(--primary-color);
            font-size: 0.9rem;
            margin-top: 0.5rem;
            opacity: 0.7;
            font-weight: 500;
        }

        .custom-file-input {
            position: relative;
            width: 100%;
        }

        .custom-file-label {
            background: white;
            border: 2px solid var(--accent-color);
            border-radius: 8px;
            padding: 0.75rem;
            cursor: pointer;
            text-align: center;
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .custom-file-label:hover {
            background: var(--accent-color);
        }

        .modal-footer {
            background: white;
            border-top: none;
            border-radius: 0 0 15px 15px;
            padding: 1.25rem 1.5rem;
            gap: 0.75rem;
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

        .btn-modal-save {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-modal-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(57, 60, 178, 0.2);
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
                    <a href="manage_candidates.php" class="nav-link active">
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
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <h2 class="mb-0" style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; font-size: 24px; color: var(--primary-color);">Manage Candidates</h2>
                        </div>
                        <button class="add-candidate-btn" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                            <i class='bx bx-plus'></i>
                            Add New Candidate
                        </button>
                    </div>
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
                <div class="row g-4">
                    <?php foreach ($candidates as $candidate): ?>
                        <div class="col-md-4">
                            <div class="candidate-card">
                                <img src="<?php echo !empty($candidate['image_path']) ? '../' . $candidate['image_path'] : '../uploads/candidates/default.png'; ?>" 
                                     alt="<?php echo htmlspecialchars($candidate['name']); ?>" 
                                     class="candidate-image">
                                <h5 class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></h5>
                                <span class="position-badge"><?php echo htmlspecialchars($candidate['position_name']); ?></span>
                                <div class="platform-text">
                                    <?php echo nl2br(htmlspecialchars($candidate['platform'])); ?>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn btn-edit" data-id="<?php echo $candidate['id']; ?>" data-name="<?php echo $candidate['name']; ?>" data-position="<?php echo $candidate['position_name']; ?>" data-platform="<?php echo $candidate['platform']; ?>">
                                        <i class='bx bx-edit'></i> Edit
                                    </button>
                                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                                        <button class="btn btn-remove" data-id="<?php echo $candidate['id']; ?>" data-name="<?php echo $candidate['name']; ?>">
                                            <i class='bx bx-trash'></i> Remove
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Candidate Modal -->
    <div class="modal fade" id="addCandidateModal" tabindex="-1" aria-labelledby="addCandidateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCandidateModalLabel">
                        <i class='bx bx-user-plus'></i>
                        Add New Candidate
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="process_candidate.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="text-center mb-4">
                            <div class="image-preview-wrapper">
                                <img src="../uploads/candidates/default.png" alt="" class="image-preview" id="imagePreview">
                                <i class='bx bxs-user-circle'></i>
                            </div>
                            <div class="image-preview-text">Click below to upload candidate photo</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="candidateImage">Candidate Photo</label>
                            <div class="custom-file-input">
                                <input type="file" class="form-control" id="candidateImage" name="image" accept="image/*" onchange="previewImage(this)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="candidateName">Full Name</label>
                            <input type="text" class="form-control" id="candidateName" name="name" required placeholder="Enter candidate's full name">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="candidatePosition">Position</label>
                            <select class="form-select" id="candidatePosition" name="position_id" required>
                                <option value="" disabled selected>Select a position</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo $position['id']; ?>">
                                        <?php echo htmlspecialchars($position['position_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label" for="candidatePlatform">Platform</label>
                            <textarea class="form-control" id="candidatePlatform" name="platform" rows="4" required 
                                placeholder="Enter candidate's platform and goals"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modal-save">Save Candidate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteCandidateModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this candidate? This action cannot be undone.</p>
                    <p class="text-danger"><strong>Candidate Name: </strong><span id="deleteModalCandidateName"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
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
                <form id="editCandidateForm" action="edit_candidate.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="candidate_id" id="editCandidateId">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="edit_position">Position</label>
                            <select class="form-select" name="position_id" id="editPosition" required>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo $position['id']; ?>">
                                        <?php echo htmlspecialchars($position['position_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_image">New Image (leave blank to keep current)</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="edit_platform">Platform</label>
                            <textarea class="form-control" name="platform" id="editPlatform" rows="4" required></textarea>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Edit candidate functionality
            $('.btn-edit').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const position = $(this).data('position');
                const platform = $(this).data('platform');
                
                // Set values in edit modal
                $('#editCandidateId').val(id);
                $('#editPosition').val(position);
                $('#editPlatform').val(platform);
                
                // Show edit modal
                $('#editCandidateModal').modal('show');
            });

            // Handle edit form submission
            $('#editCandidateForm').submit(function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: 'edit_candidate.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error updating candidate: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error updating candidate. Please try again.');
                    }
                });
            });

            // Delete candidate functionality
            let candidateToDelete = null;

            $('.btn-remove').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                candidateToDelete = id;
                
                // Set the candidate name in the modal
                $('#deleteModalCandidateName').text(name);
                
                // Show delete confirmation modal
                $('#deleteCandidateModal').modal('show');
            });

            // Handle delete confirmation
            $('#confirmDelete').click(function() {
                if (candidateToDelete) {
                    $.ajax({
                        url: 'delete_candidate.php',
                        type: 'POST',
                        data: {
                            candidate_id: candidateToDelete
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error deleting candidate: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Error deleting candidate. Please try again.');
                        }
                    });
                }
                $('#deleteCandidateModal').modal('hide');
            });
        });

        function previewImage(input) {
            const previewIcon = document.querySelector('.image-preview-wrapper i');
            const previewText = document.querySelector('.image-preview-text');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    // Hide the icon and text when an image is selected
                    previewIcon.style.display = 'none';
                    previewText.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                // Show the icon and text when no image is selected
                previewIcon.style.display = 'block';
                previewText.style.display = 'block';
            }
        }
    </script>
</body>
</html>
