<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_users'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connection.php';

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_appointments.php?error=invalid_appointment_id");
    exit;
}

$appointment_id = $_GET['id'];

// Get appointment details
$appointment_query = "SELECT a.*, 
                      CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                      CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN doctors d ON a.doctor_id = d.doctor_id
                      WHERE a.appointment_id = ?";
$stmt = $conn->prepare($appointment_query);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_appointments.php?error=appointment_not_found");
    exit;
}

$appointment = $result->fetch_assoc();

// Check if appointment is already cancelled
if ($appointment['status'] === 'Cancelled') {
    header("Location: admin_appointment_view.php?id=$appointment_id&error=already_cancelled");
    exit;
}

// Process cancellation if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update appointment status to Cancelled
    $update_query = "UPDATE appointments SET status = 'Cancelled', updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $appointment_id);
    
    if ($stmt->execute()) {
        // Log the cancellation activity
        $admin_id = $_SESSION['admin_users'];
        $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) 
                      VALUES (?, 'appointment_cancellation', ?)";
        $log_stmt = $conn->prepare($log_query);
        $details = "Cancelled appointment #$appointment_id for " . $appointment['patient_name'] . " with Dr. " . $appointment['doctor_name'];
        $log_stmt->bind_param("is", $admin_id, $details);
        $log_stmt->execute();
        
        header("Location: admin_appointment_view.php?id=$appointment_id&success=cancelled");
        exit;
    } else {
        $error = "Failed to cancel appointment. Please try again.";
    }
}

// Get admin information for the header
$admin_id = $_SESSION['admin_users'];
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment - TimeSync Admin</title>
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

        .appointment-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .detail-row {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        .detail-value {
            color: #212529;
        }
        .confirmation-box {
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
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
                    <h1 class="h2">Cancel Appointment</h1>
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <a href="#" class="btn btn-outline-secondary">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Back button -->
                <div class="mb-4">
                    <a href="admin_appointment_view.php?id=<?php echo $appointment_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Appointment
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Appointment Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="appointment-details">
                            <div class="row detail-row">
                                <div class="col-md-2 detail-label">Appointment ID:</div>
                                <div class="col-md-4 detail-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></div>
                                <div class="col-md-2 detail-label">Status:</div>
                                <div class="col-md-4 detail-value">
                                    <span class="badge bg-warning"><?php echo htmlspecialchars($appointment['status']); ?></span>
                                </div>
                            </div>
                            <div class="row detail-row">
                                <div class="col-md-2 detail-label">Patient:</div>
                                <div class="col-md-4 detail-value"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                <div class="col-md-2 detail-label">Doctor:</div>
                                <div class="col-md-4 detail-value"><?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                            </div>
                            <div class="row detail-row">
                                <div class="col-md-2 detail-label">Date & Time:</div>
                                <div class="col-md-4 detail-value">
                                    <?php 
                                        $date = new DateTime($appointment['appointment_datetime']);
                                        echo $date->format('M d, Y h:i A'); 
                                    ?>
                                </div>
                                <div class="col-md-2 detail-label">Reason:</div>
                                <div class="col-md-4 detail-value"><?php echo htmlspecialchars($appointment['reason_for_visit']); ?></div>
                            </div>
                        </div>

                        <div class="confirmation-box bg-light">
                            <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Cancellation</h5>
                            <p>Are you sure you want to cancel this appointment? This action cannot be undone.</p>
                            <p>The patient and doctor will be notified of this cancellation.</p>
                            
                            <form method="post">
                                <div class="mb-3">
                                    <label for="cancellation_reason" class="form-label">Cancellation Reason (Optional)</label>
                                    <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" placeholder="Enter reason for cancellation..."></textarea>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="admin_appointment_view.php?id=<?php echo $appointment_id; ?>" class="btn btn-outline-secondary me-2">
                                        <i class="fas fa-times me-1"></i> No, Go Back
                                    </a>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-check me-1"></i> Yes, Cancel Appointment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>