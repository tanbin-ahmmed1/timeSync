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

// Get filter parameters from URL
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query for export
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

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Handle export action
if (isset($_POST['export_csv'])) {
    // Log admin activity
    $action_details = "exported doctors data to CSV";
    $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) VALUES (?, 'EXPORT', ?)";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("is", $admin_id, $action_details);
    $log_stmt->execute();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="doctors_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, array(
        'Doctor ID',
        'Name',
        'Email',
        'Phone',
        'Department',
        'Status',
        'Registration Date',
        'Total Appointments'
    ));
    
    // Reset result pointer
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Add data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, array(
            $row['doctor_id'],
            $row['name'],
            $row['email'],
            $row['phone'],
            $row['department'],
            $row['status'],
            date('Y-m-d H:i:s', strtotime($row['registration_date'])),
            $row['appointment_count']
        ));
    }
    
    fclose($output);
    exit;
}

if (isset($_POST['export_excel'])) {
    // Log admin activity
    $action_details = "exported doctors data to Excel";
    $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) VALUES (?, 'EXPORT', ?)";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("is", $admin_id, $action_details);
    $log_stmt->execute();
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="doctors_export_' . date('Y-m-d_H-i-s') . '.xls"');
    
    // Reset result pointer
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>Doctor ID</th>";
    echo "<th>Name</th>";
    echo "<th>Email</th>";
    echo "<th>Phone</th>";
    echo "<th>Department</th>";
    echo "<th>Status</th>";
    echo "<th>Registration Date</th>";
    echo "<th>Total Appointments</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['doctor_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($row['department']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . date('Y-m-d H:i:s', strtotime($row['registration_date'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['appointment_count']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// Get doctors data for preview
$doctors = [];
while ($row = $result->fetch_assoc()) {
    $doctors[] = $row;
}

// Get available departments for display
$departments_query = "SELECT DISTINCT department FROM doctor_users WHERE department IS NOT NULL ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    if (!empty($row['department'])) {
        $departments[] = $row['department'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Doctors - TimeSync Admin</title>
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
        
        .export-card {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
        }
        
        .preview-table {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .export-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .export-option:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .export-option i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }
        
        .dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .dropdown-menu a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        
        .dropdown-menu a:hover {
            background-color: #f1f1f1;
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
                    <h1 class="h2">Export Doctors Data</h1>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user me-2"></i> Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                        </div>
                    </div>
                </div>

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="admin_doctors.php">Doctors</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Export</li>
                    </ol>
                </nav>

                <!-- Applied Filters Info -->
                <?php if (!empty($search_term) || !empty($department_filter) || !empty($status_filter)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card export-card">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-filter me-2"></i>Applied Filters</h6>
                                <div class="row">
                                    <?php if (!empty($search_term)): ?>
                                    <div class="col-md-4 mb-2">
                                        <strong>Search:</strong> <?php echo htmlspecialchars($search_term); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($department_filter)): ?>
                                    <div class="col-md-4 mb-2">
                                        <strong>Department:</strong> <?php echo htmlspecialchars($department_filter); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($status_filter)): ?>
                                    <div class="col-md-4 mb-2">
                                        <strong>Status:</strong> <?php echo htmlspecialchars($status_filter); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">The export will include <?php echo count($doctors); ?> doctors based on these filters.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Export Options -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">Choose Export Format</h4>
                    </div>
                    <div class="col-md-6 mb-3">
                        <form method="post" action="">
                            <div class="export-option" onclick="this.closest('form').submit();">
                                <i class="fas fa-file-csv text-success"></i>
                                <h5>CSV Format</h5>
                                <p class="text-muted mb-3">Export as Comma Separated Values file. Compatible with Excel, Google Sheets, and other spreadsheet applications.</p>
                                <button type="submit" name="export_csv" class="btn btn-success">
                                    <i class="fas fa-download me-2"></i>Export as CSV
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6 mb-3">
                        <form method="post" action="">
                            <div class="export-option" onclick="this.closest('form').submit();">
                                <i class="fas fa-file-excel text-primary"></i>
                                <h5>Excel Format</h5>
                                <p class="text-muted mb-3">Export as Excel file. Can be opened directly in Microsoft Excel with formatting preserved.</p>
                                <button type="submit" name="export_excel" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i>Export as Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Data Preview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-eye me-2"></i>Data Preview 
                                    <span class="badge bg-primary ms-2"><?php echo count($doctors); ?> records</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($doctors) > 0): ?>
                                <div class="table-responsive preview-table">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                                <th>Registration Date</th>
                                                <th>Appointments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($doctors as $doctor): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($doctor['doctor_id']); ?></td>
                                                <td>Dr. <?php echo htmlspecialchars($doctor['name']); ?></td>
                                                <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                                <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($doctor['department']); ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($doctor['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                        $date = new DateTime($doctor['registration_date']);
                                                        echo $date->format('M d, Y');
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $doctor['appointment_count']; ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No doctors found matching the applied filters. Please adjust your filters and try again.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <a href="admin_doctors.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Doctors
                            </a>
                            <?php if (count($doctors) > 0): ?>
                            <div class="btn-group">
                                <form method="post" action="" style="display:inline;">
                                    <button type="submit" name="export_csv" class="btn btn-success me-2">
                                        <i class="fas fa-file-csv me-2"></i>Quick Export CSV
                                    </button>
                                </form>
                                <form method="post" action="" style="display:inline;">
                                    <button type="submit" name="export_excel" class="btn btn-primary">
                                        <i class="fas fa-file-excel me-2"></i>Quick Export Excel
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Export Instructions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Export Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>CSV Format:</h6>
                                        <ul class="mb-3">
                                            <li>Compatible with Excel, Google Sheets, and other spreadsheet applications</li>
                                            <li>Lightweight file format</li>
                                            <li>Easy to import into other systems</li>
                                            <li>Preserves data integrity</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Excel Format:</h6>
                                        <ul class="mb-3">
                                            <li>Native Microsoft Excel format</li>
                                            <li>Maintains formatting and styling</li>
                                            <li>Ready for immediate use in Excel</li>
                                            <li>Includes table headers and structure</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Note:</strong> The exported data includes sensitive information. Please handle the exported files securely and in accordance with your organization's data protection policies.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>