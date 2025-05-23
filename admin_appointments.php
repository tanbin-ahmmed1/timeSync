<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_users'])) {
    header("Location: index.php");
    exit;
}

require_once 'db_connection.php';

// Process dropdown menu actions
$showUserMenu = false;
if (isset($_GET['toggle_menu']) && $_GET['toggle_menu'] == 'user') {
    $showUserMenu = true;
}

// Get admin information
$admin_id = $_SESSION['admin_users'];
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build base query - Fixed to match your actual database structure
$query = "SELECT a.appointment_id, a.appointment_datetime, a.status, a.reason_for_visit,
          p.name as patient_name, p.id as patient_id,
          d.name as doctor_name, d.id as doctor_id
          FROM appointments a
          JOIN patient_users p ON a.patient_id = p.id
          JOIN doctor_users d ON a.doctor_id = d.id";

// Add conditions based on filters
$conditions = [];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_filter)) {
    $conditions[] = "DATE(a.appointment_datetime) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

if (!empty($search_query)) {
    $conditions[] = "(p.name LIKE ? OR 
                     d.name LIKE ? OR
                     a.reason_for_visit LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Combine conditions
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Add sorting
$query .= " ORDER BY a.appointment_datetime DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

// Check if prepare was successful
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);

// Get status counts for filter tabs
$status_counts_query = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
$status_counts_result = $conn->query($status_counts_query);
$status_counts = [];
if ($status_counts_result) {
    while ($row = $status_counts_result->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - TimeSync Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="adminDashboardStyles.css">
    <style>
        /* CSS for dropdown without JavaScript */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            min-width: 10rem;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
            background-color: #fff;
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 0.25rem;
            z-index: 1000;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid #e9ecef;
        }
        
        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.25rem 1.5rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            text-decoration: none;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            color: #16181b;
            text-decoration: none;
            background-color: #f8f9fa;
        }
        
        .dropdown-item.text-danger {
            color: #dc3545;
        }
        
        /* Custom tooltip CSS */
        [data-tooltip] {
            position: relative;
            cursor: pointer;
        }
        
        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 10px;
            background-color: #000;
            color: #fff;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
            z-index: 1000;
        }
        
        [data-tooltip]:hover:before {
            opacity: 0.9;
            visibility: visible;
        }

        .status-filter {
            display: flex;
            overflow-x: auto;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .status-filter .btn {
            white-space: nowrap;
            margin-right: 8px;
            border-radius: 20px;
            padding: 5px 15px;
        }
        .status-filter .btn.active {
            font-weight: 600;
        }
        .action-btn {
            padding: 5px 10px;
            margin: 0 3px;
            font-size: 14px;
        }
        .search-box {
            position: relative;
        }
        .search-box .form-control {
            padding-left: 40px;
            border-radius: 20px;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 10px;
            color: #6c757d;
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
                            <a class="nav-link text-dark" href="admin_doctors.php">
                                <i class="fas fa-user-md me-2"></i> Doctors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_appointments.php">
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
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Appointments Management</h1>
                    <div class="d-flex align-items-center">
                        <a href="admin_appointment_add.php" class="btn btn-primary me-3">
                            <i class="fas fa-plus me-1"></i> New Appointment
                        </a>
                        <div class="dropdown">
                            <a href="?toggle_menu=user<?php echo !empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'toggle_menu') === false ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-outline-secondary <?php echo $showUserMenu ? 'active' : ''; ?>">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu <?php echo $showUserMenu ? 'show' : ''; ?>" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Filter Appointments</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" action="admin_appointments.php">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="Scheduled" <?php echo $status_filter === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="Confirmed" <?php echo $status_filter === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="search" class="form-label">Search</label>
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" class="form-control" id="search" name="search" placeholder="Search patients or doctors..." value="<?php echo htmlspecialchars($search_query); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                                <a href="admin_appointments.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Status Filter Tabs -->
                <div class="status-filter">
                    <a href="admin_appointments.php" class="btn btn-outline-secondary <?php echo empty($status_filter) ? 'active' : ''; ?>">
                        All <span class="badge bg-secondary ms-1"><?php echo array_sum($status_counts); ?></span>
                    </a>
                    <a href="admin_appointments.php?status=Scheduled" class="btn btn-outline-warning <?php echo $status_filter === 'Scheduled' ? 'active' : ''; ?>">
                        Scheduled <span class="badge bg-warning text-dark ms-1"><?php echo $status_counts['Scheduled'] ?? 0; ?></span>
                    </a>
                    <a href="admin_appointments.php?status=Confirmed" class="btn btn-outline-success <?php echo $status_filter === 'Confirmed' ? 'active' : ''; ?>">
                        Confirmed <span class="badge bg-success ms-1"><?php echo $status_counts['Confirmed'] ?? 0; ?></span>
                    </a>
                    <a href="admin_appointments.php?status=Cancelled" class="btn btn-outline-danger <?php echo $status_filter === 'Cancelled' ? 'active' : ''; ?>">
                        Cancelled <span class="badge bg-danger ms-1"><?php echo $status_counts['Cancelled'] ?? 0; ?></span>
                    </a>
                    <a href="admin_appointments.php?status=Completed" class="btn btn-outline-info <?php echo $status_filter === 'Completed' ? 'active' : ''; ?>">
                        Completed <span class="badge bg-info ms-1"><?php echo $status_counts['Completed'] ?? 0; ?></span>
                    </a>
                </div>

                <!-- Appointments Table -->
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Appointments List</h5>
                        <div>
                            <span class="me-2">Total: <?php echo count($appointments); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Date & Time</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($appointments) > 0): ?>
                                        <?php foreach ($appointments as $appt): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($appt['appointment_id']); ?></td>
                                                <td>
                                                    <a href="admin_patient_view.php?id=<?php echo $appt['patient_id']; ?>">
                                                        <?php echo htmlspecialchars($appt['patient_name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="admin_doctor_view.php?id=<?php echo $appt['doctor_id']; ?>">
                                                        <?php echo htmlspecialchars($appt['doctor_name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $date = new DateTime($appt['appointment_datetime']);
                                                        echo $date->format('M d, Y h:i A'); 
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($appt['reason_for_visit'], 0, 30)); ?><?php echo strlen($appt['reason_for_visit']) > 30 ? '...' : ''; ?></td>
                                                <td>
                                                    <?php 
                                                        $status_class = '';
                                                        switch($appt['status']) {
                                                            case 'Scheduled':
                                                                $status_class = 'warning';
                                                                break;
                                                            case 'Confirmed':
                                                                $status_class = 'success';
                                                                break;
                                                            case 'Cancelled':
                                                                $status_class = 'danger';
                                                                break;
                                                            case 'Completed':
                                                                $status_class = 'info';
                                                                break;
                                                            default:
                                                                $status_class = 'secondary';
                                                        }
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($appt['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="admin_appointment_view.php?id=<?php echo $appt['appointment_id']; ?>" class="btn btn-sm btn-outline-primary action-btn" data-tooltip="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="admin_appointment_edit.php?id=<?php echo $appt['appointment_id']; ?>" class="btn btn-sm btn-outline-secondary action-btn" data-tooltip="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($appt['status'] !== 'Cancelled'): ?>
                                                        <a href="admin_appointment_cancel.php?id=<?php echo $appt['appointment_id']; ?>" class="btn btn-sm btn-outline-danger action-btn" data-tooltip="Cancel" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No appointments found matching your criteria</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>