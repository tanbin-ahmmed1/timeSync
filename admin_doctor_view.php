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

// Check if doctor ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_doctors.php");
    exit;
}

$doctor_id = $_GET['id'];

// Get doctor details
$doctor_query = "SELECT d.id, d.name, d.username, d.email, d.phone, d.department, d.created_at, 
                COUNT(a.appointment_id) as appointment_count 
                FROM doctor_users d 
                LEFT JOIN appointments a ON d.id = a.doctor_id 
                WHERE d.id = ? 
                GROUP BY d.id";
$stmt = $conn->prepare($doctor_query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    // Doctor not found, redirect to doctors list
    header("Location: admin_doctors.php");
    exit;
}

$doctor_data = $result->fetch_assoc();

// Get recent appointments for this doctor
$appointments_query = "SELECT a.appointment_id, a.appointment_datetime, a.status, 
                      p.name as patient_name, p.id as patient_id
                      FROM appointments a 
                      JOIN patient_users p ON a.patient_id = p.id
                      WHERE a.doctor_id = ? 
                      ORDER BY a.appointment_datetime DESC LIMIT 5";
$stmt = $conn->prepare($appointments_query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$appointments_result = $stmt->get_result();
$recent_appointments = [];
while($appointment = $appointments_result->fetch_assoc()) {
    $recent_appointments[] = $appointment;
}

// Get appointment stats
$stats_query = "SELECT 
                COUNT(*) as total_appointments, 
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_appointments,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
                SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled_appointments
                FROM appointments 
                WHERE doctor_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$appointment_stats = $stats_result->fetch_assoc();

// Get admin activity related to this doctor
$logs_query = "SELECT action_type, action_details, timestamp 
              FROM admin_activity_log 
              WHERE action_details LIKE ? 
              ORDER BY timestamp DESC LIMIT 10";
$log_param = "%doctor ID: $doctor_id%";
$stmt = $conn->prepare($logs_query);
$stmt->bind_param("s", $log_param);
$stmt->execute();
$logs_result = $stmt->get_result();
$activity_logs = [];
while($log = $logs_result->fetch_assoc()) {
    $activity_logs[] = $log;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Details - TimeSync Admin</title>
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
        
        .profile-header {
            position: relative;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-left: 5px solid #007bff;
        }
        
        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 3rem;
        }
        
        .appointment-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s ease;
        }
        
        .appointment-card:hover {
            transform: translateY(-3px);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        .info-card {
            height: 100%;
            transition: transform 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
        }
        
        .activity-item {
            border-left: 3px solid #6c757d;
            padding-left: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .activity-item:before {
            content: "";
            position: absolute;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
            left: -7px;
            top: 0.5rem;
        }
        
        .activity-item.create {
            border-left-color: #28a745;
        }
        
        .activity-item.create:before {
            background: #28a745;
        }
        
        .activity-item.update {
            border-left-color: #ffc107;
        }
        
        .activity-item.update:before {
            background: #ffc107;
        }
        
        .activity-item.delete {
            border-left-color: #dc3545;
        }
        
        .activity-item.delete:before {
            background: #dc3545;
        }
        
        .stat-card {
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
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
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="admin_doctors.php">Doctors</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Dr. <?php echo htmlspecialchars($doctor_data['name']); ?></li>
                            </ol>
                        </nav>
                        <h1 class="h2">Doctor Profile</h1>
                    </div>
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

                <!-- Action buttons -->
                <div class="d-flex justify-content-end mb-4">
                    <a href="admin_doctor_edit.php?id=<?php echo $doctor_id; ?>" class="btn btn-warning me-2">
                        <i class="fas fa-edit me-2"></i>Edit Doctor
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="fas fa-trash-alt me-2"></i>Delete Doctor
                    </button>
                </div>

                <!-- Doctor Profile Header -->
                <div class="profile-header">
                    <div class="row">
                        <div class="col-md-2 text-center">
                            <div class="profile-image">
                                <i class="fas fa-user-md"></i>
                            </div>
                        </div>
                        <div class="col-md-10">
                            <h2>Dr. <?php echo htmlspecialchars($doctor_data['name']); ?></h2>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($doctor_data['department']); ?></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><i class="fas fa-envelope me-2 text-primary"></i> <?php echo htmlspecialchars($doctor_data['email']); ?></p>
                                    <p><i class="fas fa-phone me-2 text-primary"></i> <?php echo htmlspecialchars($doctor_data['phone']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><i class="fas fa-user-circle me-2 text-primary"></i> Username: <?php echo htmlspecialchars($doctor_data['username']); ?></p>
                                    <p>
                                        <i class="fas fa-calendar-alt me-2 text-primary"></i> 
                                        Registered: 
                                        <?php 
                                            $date = new DateTime($doctor_data['created_at']);
                                            echo $date->format('F d, Y'); 
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">Appointment Statistics</h4>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total</h6>
                                    <h3 class="mb-0"><?php echo intval($appointment_stats['total_appointments'] ?? 0); ?></h3>
                                </div>
                                <div class="stat-icon bg-white">
                                    <i class="fas fa-calendar-check text-primary fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Completed</h6>
                                    <h3 class="mb-0"><?php echo intval($appointment_stats['completed_appointments'] ?? 0); ?></h3>
                                </div>
                                <div class="stat-icon bg-white">
                                    <i class="fas fa-check-circle text-success fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-dark p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Scheduled</h6>
                                    <h3 class="mb-0"><?php echo intval($appointment_stats['scheduled_appointments'] ?? 0); ?></h3>
                                </div>
                                <div class="stat-icon bg-white">
                                    <i class="fas fa-clock text-warning fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-danger text-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Cancelled</h6>
                                    <h3 class="mb-0"><?php echo intval($appointment_stats['cancelled_appointments'] ?? 0); ?></h3>
                                </div>
                                <div class="stat-icon bg-white">
                                    <i class="fas fa-ban text-danger fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Appointments -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Recent Appointments</h5>
                                <a href="admin_appointments.php?doctor_id=<?php echo $doctor_id; ?>" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_appointments) > 0): ?>
                                    <?php foreach($recent_appointments as $appointment): ?>
                                        <div class="card mb-3 appointment-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <a href="admin_appointment_view.php?id=<?php echo $appointment['appointment_id']; ?>">
                                                                Appointment #<?php echo $appointment['appointment_id']; ?>
                                                            </a>
                                                        </h6>
                                                        <p class="mb-1">
                                                            <a href="admin_user_view.php?id=<?php echo $appointment['patient_id']; ?>" class="text-decoration-none">
                                                                <i class="fas fa-user me-1"></i> 
                                                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                            </a>
                                                        </p>
                                                        <p class="text-muted mb-0">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php 
                                                                $date = new DateTime($appointment['appointment_datetime']);
                                                                echo $date->format('M d, Y - h:i A'); 
                                                            ?>
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <?php 
                                                            $status_class = '';
                                                            switch($appointment['status']) {
                                                                case 'Completed':
                                                                    $status_class = 'bg-success';
                                                                    break;
                                                                case 'Scheduled':
                                                                    $status_class = 'bg-warning text-dark';
                                                                    break;
                                                                case 'Cancelled':
                                                                    $status_class = 'bg-danger';
                                                                    break;
                                                                default:
                                                                    $status_class = 'bg-secondary';
                                                            }
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?> status-badge">
                                                            <?php echo htmlspecialchars($appointment['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No appointments found for this doctor.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Activity Log -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Admin Activity Log</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($activity_logs) > 0): ?>
                                    <div class="timeline">
                                        <?php foreach($activity_logs as $log): ?>
                                            <div class="activity-item <?php echo strtolower($log['action_type']); ?>">
                                                <p class="mb-1 fw-bold">
                                                    <?php
                                                        $action_icon = '';
                                                        switch($log['action_type']) {
                                                            case 'CREATE':
                                                                $action_icon = '<i class="fas fa-plus-circle text-success me-1"></i>';
                                                                break;
                                                            case 'UPDATE':
                                                                $action_icon = '<i class="fas fa-edit text-warning me-1"></i>';
                                                                break;
                                                            case 'DELETE':
                                                                $action_icon = '<i class="fas fa-trash-alt text-danger me-1"></i>';
                                                                break;
                                                            default:
                                                                $action_icon = '<i class="fas fa-cog text-secondary me-1"></i>';
                                                        }
                                                        echo $action_icon . $log['action_type'];
                                                    ?>
                                                </p>
                                                <p class="mb-1"><?php echo htmlspecialchars($log['action_details']); ?></p>
                                                <small class="text-muted">
                                                    <?php 
                                                        $date = new DateTime($log['timestamp']);
                                                        echo $date->format('M d, Y - h:i A'); 
                                                    ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No activity logs found for this doctor.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Doctor Info Card -->
                        <div class="card info-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Additional Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Doctor ID:</strong> <?php echo $doctor_data['id']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Total Appointments:</strong> <?php echo $doctor_data['appointment_count']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Account Status:</strong> 
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div>
                                    <strong>Registration Date:</strong> 
                                    <?php 
                                        $date = new DateTime($doctor_data['created_at']);
                                        echo $date->format('F d, Y'); 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Doctor Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete Dr. <?php echo htmlspecialchars($doctor_data['name']); ?>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. All doctor information will be permanently removed.
                    </p>
                    <?php if ($doctor_data['appointment_count'] > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            This doctor has <?php echo $doctor_data['appointment_count']; ?> appointments in the system.
                            Deleting this doctor will affect these appointments.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="admin_doctors.php?action=delete&id=<?php echo $doctor_id; ?>" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i>Delete Doctor
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>