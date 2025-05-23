<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_users'])) {
    header("Location: index.php");
    exit;
}

require_once 'db_connection.php';

// Get appointment ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_appointments.php");
    exit;
}

$appointment_id = intval($_GET['id']);

// Get admin information
$admin_id = $_SESSION['admin_users'];
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

// Get appointment details
$query = "SELECT a.appointment_id, a.appointment_datetime, a.status, a.reason_for_visit, a.created_at, a.updated_at,
          CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_id, p.date_of_birth, p.gender, p.contact_number as patient_phone, p.email as patient_email,
          CONCAT(d.first_name, ' ', d.last_name) as doctor_name, d.doctor_id, d.specialty, d.contact_number as doctor_phone, d.email as doctor_email
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN doctors d ON a.doctor_id = d.doctor_id
          WHERE a.appointment_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    header("Location: admin_appointments.php");
    exit;
}

// Format dates
$appointment_datetime = new DateTime($appointment['appointment_datetime']);
$created_at = new DateTime($appointment['created_at']);
$updated_at = new DateTime($appointment['updated_at']);

// Process dropdown menu actions
$showUserMenu = false;
if (isset($_GET['toggle_menu']) && $_GET['toggle_menu'] == 'user') {
    $showUserMenu = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - TimeSync Admin</title>
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

        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
        
        .detail-card {
            margin-bottom: 20px;
            border-radius: 10px;
        }
        
        .detail-card .card-header {
            font-weight: 600;
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            width: 150px;
            color: #6c757d;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .action-buttons {
            margin-top: 20px;
        }
        
        .history-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .history-item:last-child {
            border-bottom: none;
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
                    <h1 class="h2">Appointment Details</h1>
                    <div class="d-flex align-items-center">
                        <a href="admin_appointments.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left me-1"></i> Back to Appointments
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

                <!-- Status Badge -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <?php 
                        $status_class = '';
                        switch($appointment['status']) {
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
                    <span class="badge bg-<?php echo $status_class; ?> status-badge">
                        <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                    </span>
                    <div>
                        <small class="text-muted">Created: <?php echo $created_at->format('M d, Y h:i A'); ?></small>
                        <?php if ($appointment['created_at'] != $appointment['updated_at']): ?>
                            <small class="text-muted ms-3">Last Updated: <?php echo $updated_at->format('M d, Y h:i A'); ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Appointment Details -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow detail-card">
                            <div class="card-header">
                                <i class="fas fa-calendar-alt me-2"></i> Appointment Information
                            </div>
                            <div class="card-body">
                                <div class="detail-row">
                                    <div class="detail-label">Appointment ID</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Date & Time</div>
                                    <div class="detail-value"><?php echo $appointment_datetime->format('l, F j, Y \a\t h:i A'); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Reason for Visit</div>
                                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($appointment['reason_for_visit'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow detail-card">
                            <div class="card-header">
                                <i class="fas fa-user me-2"></i> Patient Information
                            </div>
                            <div class="card-body">
                                <div class="detail-row">
                                    <div class="detail-label">Patient Name</div>
                                    <div class="detail-value">
                                        <a href="admin_patient_view.php?id=<?php echo $appointment['patient_id']; ?>">
                                            <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Date of Birth</div>
                                    <div class="detail-value">
                                        <?php 
                                            if ($appointment['date_of_birth']) {
                                                $dob = new DateTime($appointment['date_of_birth']);
                                                echo $dob->format('m/d/Y');
                                            } else {
                                                echo 'Not specified';
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Gender</div>
                                    <div class="detail-value"><?php echo $appointment['gender'] ? htmlspecialchars($appointment['gender']) : 'Not specified'; ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Contact</div>
                                    <div class="detail-value">
                                        <?php echo $appointment['patient_phone'] ? htmlspecialchars($appointment['patient_phone']) : 'Not specified'; ?><br>
                                        <?php echo htmlspecialchars($appointment['patient_email']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow detail-card">
                            <div class="card-header">
                                <i class="fas fa-user-md me-2"></i> Doctor Information
                            </div>
                            <div class="card-body">
                                <div class="detail-row">
                                    <div class="detail-label">Doctor Name</div>
                                    <div class="detail-value">
                                        <a href="admin_doctor_view.php?id=<?php echo $appointment['doctor_id']; ?>">
                                            <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Specialty</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($appointment['specialty']); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Contact</div>
                                    <div class="detail-value">
                                        <?php echo $appointment['doctor_phone'] ? htmlspecialchars($appointment['doctor_phone']) : 'Not specified'; ?><br>
                                        <?php echo htmlspecialchars($appointment['doctor_email']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow detail-card">
                            <div class="card-header">
                                <i class="fas fa-history me-2"></i> Appointment History
                            </div>
                            <div class="card-body">
                                <?php
                                // Get appointment history
                                $history_query = "SELECT action_type, action_details, timestamp 
                                                 FROM admin_activity_log 
                                                 WHERE action_type LIKE '%appointment%' AND action_details LIKE ? 
                                                 ORDER BY timestamp DESC";
                                $stmt = $conn->prepare($history_query);
                                $search_param = "%appointment_id={$appointment['appointment_id']}%";
                                $stmt->bind_param("s", $search_param);
                                $stmt->execute();
                                $history_result = $stmt->get_result();
                                
                                if ($history_result->num_rows > 0) {
                                    while ($history = $history_result->fetch_assoc()) {
                                        $history_time = new DateTime($history['timestamp']);
                                        echo '<div class="history-item">';
                                        echo '<div class="fw-bold">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $history['action_type']))) . '</div>';
                                        echo '<div class="text-muted small">' . $history_time->format('M d, Y h:i A') . '</div>';
                                        if (!empty($history['action_details'])) {
                                            echo '<div class="mt-1">' . htmlspecialchars($history['action_details']) . '</div>';
                                        }
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="text-muted">No history records found for this appointment.</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons text-end">
                    <a href="admin_appointment_edit.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Edit Appointment
                    </a>
                    <?php if ($appointment['status'] !== 'Cancelled'): ?>
                        <a href="admin_appointment_cancel.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-danger ms-2" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                            <i class="fas fa-times me-1"></i> Cancel Appointment
                        </a>
                    <?php endif; ?>
                    <?php if ($appointment['status'] === 'Scheduled' || $appointment['status'] === 'Confirmed'): ?>
                        <a href="admin_appointment_complete.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-success ms-2" onclick="return confirm('Mark this appointment as completed?');">
                            <i class="fas fa-check me-1"></i> Mark as Completed
                        </a>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>