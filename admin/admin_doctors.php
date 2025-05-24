<?php
session_start();

// Check if admin is logged in, if not redirect to login page
if (!isset($_SESSION['admin_users']) || empty($_SESSION['admin_users'])) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get admin information
$admin_id = $_SESSION['admin_users'];
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

// Process doctor actions (add, edit, delete)
$message = '';
$message_type = '';

// Handle doctor deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $doctor_id = $_GET['id'];
    
    // Check if doctor has any appointments before deletion
    $check_appointments = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?";
    $stmt = $conn->prepare($check_appointments);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment_count = $result->fetch_assoc()['count'];
    
    if ($appointment_count > 0) {
        $message = "Cannot delete doctor. They have $appointment_count appointments in the system.";
        $message_type = "danger";
    } else {
        // Delete doctor user
        $delete_query = "DELETE FROM doctor_users WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $doctor_id);
        
        if ($stmt->execute()) {
            // Log admin activity
            $action_details = "deleted doctor ID: $doctor_id";
            $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) VALUES (?, 'DELETE', ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param("is", $admin_id, $action_details);
            $log_stmt->execute();
            
            $message = "Doctor deleted successfully.";
            $message_type = "success";
        } else {
            $message = "Error deleting doctor: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// Get filter and search parameters
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query
$query = "SELECT d.id as doctor_id, 
          d.name, d.email, d.phone, 
          d.department, 
          'Active' as status, d.created_at as registration_date, 
          COUNT(a.appointment_id) as appointment_count
          FROM doctor_users d
          LEFT JOIN appointments a ON d.id = a.doctor_id";

// Add WHERE clauses based on filters
$where_clauses = [];
$params = [];
$types = "";

if (!empty($department_filter)) {
    $where_clauses[] = "d.department = ?";
    $params[] = $department_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where_clauses[] = "'Active' = ?"; // Using constant as status is not in database
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_term)) {
    $where_clauses[] = "(d.name LIKE ? OR d.email LIKE ? OR d.phone LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " GROUP BY d.id ORDER BY d.name";

// Get available departments for filter dropdown
$departments_query = "SELECT DISTINCT department FROM doctor_users WHERE department IS NOT NULL ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    if (!empty($row['department'])) {
        $departments[] = $row['department'];
    }
}

// Prepare and execute the main query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$doctors = [];
while ($row = $result->fetch_assoc()) {
    $doctors[] = $row;
}

// Count total doctors
$count_query = "SELECT COUNT(*) as total FROM doctor_users";
$count_result = $conn->query($count_query);
$total_doctors = $count_result->fetch_assoc()['total'];

// Count active doctors (all doctors are considered active in this simplified version)
$active_doctors = $total_doctors;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - TimeSync Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .nav-link {
            font-weight: 500;
            color: #333;
        }
        
        .nav-link.active {
            color: #007bff;
        }
        
        main {
            padding-top: 1.5rem;
        }
        
        .dashboard-card .icon-bg {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .status-active {
            color: #28a745;
        }
        
        .status-inactive {
            color: #dc3545;
        }
        
        .status-pending {
            color: #ffc107;
        }
        
        .doctor-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .filter-card {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="TimeSync.png" alt="TimeSync Logo">
                        <h5 class="mt-2">TimeSync <br> Admin Panel</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_doctors.php">
                                <i class="fas fa-user-md me-2"></i> Doctors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="admin_appointments.php">
                                <i class="fas fa-calendar-check me-2"></i> Appointments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="admin_users.php">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="admin_system_stats.php">
                                <i class="fas fa-chart-line me-2"></i> System Statistics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="admin_settings.php">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="admin_activity_logs.php">
                                <i class="fas fa-history me-2"></i> Activity Logs
                            </a>
                        </li>
                        <li class="nav-item mt-5">
                            <a class="nav-link text-danger" href="admin_logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Doctors</h1>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Total Doctors</h6>
                                        <h2 class="mb-0"><?php echo $total_doctors; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-white">
                                        <i class="fas fa-user-md fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Active Doctors</h6>
                                        <h2 class="mb-0"><?php echo $active_doctors; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-white">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Inactive Doctors</h6>
                                        <h2 class="mb-0"><?php echo $total_doctors - $active_doctors; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-white">
                                        <i class="fas fa-pause-circle fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card filter-card">
                            <div class="card-body">
                                <form action="admin_doctors.php" method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label for="search" class="form-label">Search</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($search_term); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="department" class="form-label">Department</label>
                                        <select class="form-select" id="department" name="department">
                                            <option value="">All Departments</option>
                                            <?php foreach($departments as $department): ?>
                                                <option value="<?php echo htmlspecialchars($department); ?>" <?php echo ($department_filter == $department) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($department); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">All Status</option>
                                            <option value="Active" <?php echo ($status_filter == 'Active') ? 'selected' : ''; ?>>Active</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="d-grid gap-2 w-100">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-filter me-2"></i>Apply
                                            </button>
                                            <a href="admin_doctors.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-redo me-2"></i>Reset
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>Doctor List</h4>
                                <p class="text-muted">
                                    <?php 
                                    echo count($doctors) . " doctor" . (count($doctors) != 1 ? "s" : "") . " found";
                                    if (!empty($search_term) || !empty($department_filter) || !empty($status_filter)) {
                                        echo " with applied filters";
                                    }
                                    ?>
                                </p>
                            </div>
                            <div>
                                <a href="admin_doctor_add.php" class="btn btn-success">
                                    <i class="fas fa-plus-circle me-2"></i>Add New Doctor
                                </a>
                                <button class="btn btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#importModal">
                                    <i class="fas fa-file-import me-2"></i>Import
                                </button>
                                <button class="btn btn-outline-primary ms-2" id="exportBtn">
                                    <i class="fas fa-file-export me-2"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doctors List -->
                <div class="row">
                    <?php if (count($doctors) > 0): ?>
                        <?php foreach($doctors as $doctor): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card doctor-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h5>
                                                <p class="card-subtitle text-muted"><?php echo htmlspecialchars($doctor['department']); ?></p>
                                            </div>
                                            <div>
                                                <span class="status-active">
                                                    <i class="fas fa-circle me-1"></i><?php echo htmlspecialchars($doctor['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-envelope text-primary me-2"></i>
                                                <a href="mailto:<?php echo htmlspecialchars($doctor['email']); ?>"><?php echo htmlspecialchars($doctor['email']); ?></a>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-phone text-primary me-2"></i>
                                                <a href="tel:<?php echo htmlspecialchars($doctor['phone']); ?>"><?php echo htmlspecialchars($doctor['phone']); ?></a>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                                <span>
                                                    <?php
                                                        $date = new DateTime($doctor['registration_date']);
                                                        echo "Registered on " . $date->format('M d, Y');
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-calendar-check text-primary me-2"></i>
                                                <span><?php echo $doctor['appointment_count']; ?> appointments</span>
                                            </div>
                                        </div>
                                        
                                        <div class="btn-group w-100" role="group">
                                            <a href="admin_doctor_view.php?id=<?php echo $doctor['doctor_id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                            <a href="admin_doctor_edit.php?id=<?php echo $doctor['doctor_id']; ?>" class="btn btn-outline-warning">
                                                <i class="fas fa-edit me-1"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $doctor['doctor_id']; ?>">
                                                <i class="fas fa-trash-alt me-1"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete Confirmation Modal -->
                            <div class="modal fade" id="deleteModal<?php echo $doctor['doctor_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $doctor['doctor_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $doctor['doctor_id']; ?>">Confirm Delete</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete Dr. <?php echo htmlspecialchars($doctor['name']); ?>?</p>
                                            <p class="text-danger">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                This action cannot be undone. All doctor information will be permanently removed.
                                            </p>
                                            <?php if ($doctor['appointment_count'] > 0): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-circle me-2"></i>
                                                    This doctor has <?php echo $doctor['appointment_count']; ?> appointments in the system.
                                                    Deleting this doctor will affect these appointments.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <a href="admin_doctors.php?action=delete&id=<?php echo $doctor['doctor_id']; ?>" class="btn btn-danger">
                                                <i class="fas fa-trash-alt me-2"></i>Delete Doctor
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No doctors found matching your criteria.
                                <?php if (!empty($search_term) || !empty($department_filter) || !empty($status_filter)): ?>
                                    <a href="admin_doctors.php" class="alert-link">Clear all filters</a>
                                <?php else: ?>
                                    <a href="admin_doctor_add.php" class="alert-link">Add a new doctor</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Doctors</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="admin_doctor_import.php" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="importFile" class="form-label">Select CSV File</label>
                            <input class="form-control" type="file" id="importFile" name="importFile" accept=".csv">
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Please make sure your CSV file has the following columns: 
                            name, email, phone, department
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-import me-2"></i>Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        
        // Handle export button
        document.getElementById('exportBtn').addEventListener('click', function() {
            // Get current filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            const search = urlParams.get('search') || '';
            const department = urlParams.get('department') || '';
            const status = urlParams.get('status') || '';
            
            // Redirect to export script with current filters
            window.location.href = `admin_doctor_export.php?search=${search}&department=${department}&status=${status}`;
        });
    </script>
</body>
</html>