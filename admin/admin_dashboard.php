<?php
session_start();

// Check if admin is logged in, if not redirect to login page
if (!isset($_SESSION['admin_logged_in']) || empty($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Process dropdown menu actions
$showUserMenu = false;
if (isset($_GET['toggle_menu']) && $_GET['toggle_menu'] == 'user') {
    $showUserMenu = true;
}

// Get admin information
$admin_id = $_SESSION['admin_id'];
// Using admin_users table as per the database schema
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

// Get counts for dashboard widgets
// Count doctors
$doctor_query = "SELECT COUNT(*) as doctor_count FROM doctor_users";
$doctor_result = $conn->query($doctor_query);
$doctor_count = $doctor_result->fetch_assoc()['doctor_count'];

// Count patients
$patient_query = "SELECT COUNT(*) as patient_count FROM patient_users";
$patient_result = $conn->query($patient_query);
$patient_count = $patient_result->fetch_assoc()['patient_count'];

// Count today's appointments
$today = date('Y-m-d');
$today_appt_query = "SELECT COUNT(*) as today_count FROM appointments WHERE DATE(appointment_datetime) = ?";
$stmt = $conn->prepare($today_appt_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$today_appointments = $result->fetch_assoc()['today_count'];

// Count pending appointments
$pending_appt_query = "SELECT COUNT(*) as pending_count FROM appointments WHERE status = 'Scheduled'"; 
// Using 'Scheduled' as appears to be the default status in the DB
$pending_result = $conn->query($pending_appt_query);
$pending_appointments = $pending_result->fetch_assoc()['pending_count'];

// Get recent appointments
$recent_appt_query = "SELECT a.appointment_id, a.appointment_datetime, a.status, 
                     p.name as patient_name, 
                     d.name as doctor_name
                     FROM appointments a
                     JOIN patient_users p ON a.patient_id = p.id
                     JOIN doctor_users d ON a.doctor_id = d.id
                     ORDER BY a.appointment_datetime DESC
                     LIMIT 5";
$recent_appts = $conn->query($recent_appt_query);

// Get recent admin activities
$activity_query = "SELECT l.action_type, l.timestamp, l.action_details, a.full_name
                  FROM admin_activity_log l
                  JOIN administrators a ON l.admin_id = a.admin_id
                  ORDER BY l.timestamp DESC
                  LIMIT 5";
$recent_activities = $conn->query($activity_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TimeSync</title>
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
                            <a class="nav-link active" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="admin_doctors.php">
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="dropdown">
                        <a href="?toggle_menu=user" class="btn btn-outline-secondary <?php echo $showUserMenu ? 'active' : ''; ?>">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu <?php echo $showUserMenu ? 'show' : ''; ?>" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Dashboard Cards -->
                <div class="row mb-4">
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="dashboard-card card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Doctors</h6>
                                        <h2 class="mb-0"><?php echo $doctor_count; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-white">
                                        <i class="fas fa-user-md fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="admin_doctors.php" class="text-white">View Details <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="dashboard-card card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Patients</h6>
                                        <h2 class="mb-0"><?php echo $patient_count; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-white">
                                        <i class="fas fa-users fa-2x text-success"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="admin_users.php" class="text-white">View Details <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="dashboard-card card bg-warning text-dark h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Today's Appointments</h6>
                                        <h2 class="mb-0"><?php echo $today_appointments; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-white">
                                        <i class="fas fa-calendar-day fa-2x text-warning"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="admin_appointments.php?date=<?php echo $today; ?>" class="text-dark">View Details <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="dashboard-card card bg-danger text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Pending Appointments</h6>
                                        <h2 class="mb-0"><?php echo $pending_appointments; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-white">
                                        <i class="fas fa-clock fa-2x text-danger"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="admin_appointments.php?status=Scheduled" class="text-white">View Details <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Appointments & Activity Log -->
                <div class="row">
                    <!-- Recent Appointments -->
                    <div class="col-lg-7 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Appointments</h5>
                                <a href="admin_appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Doctor</th>
                                                <th>Date & Time</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recent_appts && $recent_appts->num_rows > 0): ?>
                                                <?php while ($appt = $recent_appts->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($appt['patient_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                                                        <td>
                                                            <?php 
                                                                $date = new DateTime($appt['appointment_datetime']);
                                                                echo $date->format('M d, Y h:i A'); 
                                                            ?>
                                                        </td>
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
                                                            <a href="admin_appointment_edit.php?id=<?php echo $appt['appointment_id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary" 
                                                               data-tooltip="Edit Appointment">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No recent appointments found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Admin Activity -->
                    <div class="col-lg-5 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Activity</h5>
                                <a href="admin_activity_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                                        <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <?php
                                                            $icon_class = '';
                                                            switch($activity['action_type']) {
                                                                case 'CREATE':
                                                                    $icon_class = 'fa-plus-circle text-success';
                                                                    break;
                                                                case 'UPDATE':
                                                                    $icon_class = 'fa-edit text-warning';
                                                                    break;
                                                                case 'DELETE':
                                                                    $icon_class = 'fa-trash-alt text-danger';
                                                                    break;
                                                                default:
                                                                    $icon_class = 'fa-info-circle text-info';
                                                            }
                                                        ?>
                                                        <i class="fas <?php echo $icon_class; ?> me-2"></i>
                                                        <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                                        <?php echo htmlspecialchars($activity['action_details']); ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php
                                                            $activity_time = new DateTime($activity['timestamp']);
                                                            echo $activity_time->format('M d, h:i A');
                                                        ?>
                                                    </small>
                                                </div>
                                            </li>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-center">No recent activity found</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>