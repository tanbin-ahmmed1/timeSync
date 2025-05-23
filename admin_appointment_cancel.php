<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_users'])) {
    header("Location: index.php");
    exit;
}

require_once 'db_connection.php';

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_appointments.php?error=invalid_appointment_id");
    exit;
}

$appointment_id = $_GET['id'];

// Get appointment details - Fixed to match actual database structure
$appointment_query = "SELECT a.*, 
                      p.name as patient_name, p.id as patient_id,
                      d.name as doctor_name, d.id as doctor_id
                      FROM appointments a
                      JOIN patient_users p ON a.patient_id = p.id
                      JOIN doctor_users d ON a.doctor_id = d.id
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
    $cancellation_reason = isset($_POST['cancellation_reason']) ? trim($_POST['cancellation_reason']) : '';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update appointment status to Cancelled
        $update_query = "UPDATE appointments SET status = 'Cancelled', updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $appointment_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update appointment status");
        }
        
        // If cancellation reason is provided, store it (only if the column exists)
        if (!empty($cancellation_reason)) {
            // Check if cancellation_reason column exists before using it
            $column_check = $conn->query("SHOW COLUMNS FROM appointments LIKE 'cancellation_reason'");
            if ($column_check && $column_check->num_rows > 0) {
                $reason_query = "UPDATE appointments SET cancellation_reason = ? WHERE appointment_id = ?";
                $reason_stmt = $conn->prepare($reason_query);
                if ($reason_stmt) {
                    $reason_stmt->bind_param("si", $cancellation_reason, $appointment_id);
                    $reason_stmt->execute();
                }
            }
        }
        
        // Log the cancellation activity (only if the table exists)
        $admin_id = $_SESSION['admin_users'];
        $table_check = $conn->query("SHOW TABLES LIKE 'admin_activity_log'");
        if ($table_check && $table_check->num_rows > 0) {
            $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) 
                          VALUES (?, 'appointment_cancellation', ?)";
            $log_stmt = $conn->prepare($log_query);
            if ($log_stmt) {
                $details = "Cancelled appointment #$appointment_id for " . $appointment['patient_name'] . " with Dr. " . $appointment['doctor_name'];
                if (!empty($cancellation_reason)) {
                    $details .= " - Reason: " . $cancellation_reason;
                }
                $log_stmt->bind_param("is", $admin_id, $details);
                
                if (!$log_stmt->execute()) {
                    throw new Exception("Failed to log activity");
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        header("Location: admin_appointment_view.php?id=$appointment_id&success=cancelled");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Failed to cancel appointment. Please try again.";
        // Optionally log the actual error for debugging (don't show to user)
        error_log("Appointment cancellation error: " . $e->getMessage());
    }
}

// Process dropdown menu actions
$showUserMenu = false;
if (isset($_GET['toggle_menu']) && $_GET['toggle_menu'] == 'user') {
    $showUserMenu = true;
}

// Get admin information for the header
$admin_id = $_SESSION['admin_users'];
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);

// Check if prepare was successful
if ($stmt === false) {
    die("Database error: Unable to prepare admin query. " . $conn->error);
}

$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Admin user not found.");
}

$admin_data = $result->fetch_assoc();

// Check if appointment date has passed
$appointment_datetime = new DateTime($appointment['appointment_datetime']);
$current_datetime = new DateTime();
$is_past_appointment = $appointment_datetime < $current_datetime;
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
        .past-appointment-warning {
            border-left: 4px solid #ffc107;
            background-color: #fff3cd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
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
                            <a href="?id=<?php echo $appointment_id; ?>&toggle_menu=user" class="btn btn-outline-secondary <?php echo $showUserMenu ? 'active' : ''; ?>">
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

                <!-- Breadcrumb Navigation -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="admin_appointments.php">Appointments</a></li>
                        <li class="breadcrumb-item"><a href="admin_appointment_view.php?id=<?php echo $appointment_id; ?>">Appointment #<?php echo $appointment_id; ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Cancel</li>
                    </ol>
                </nav>

                <!-- Back button -->
                <div class="mb-4">
                    <a href="admin_appointment_view.php?id=<?php echo $appointment_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Appointment
                    </a>
                    <a href="admin_appointments.php" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-list me-1"></i> All Appointments
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($is_past_appointment): ?>
                    <div class="past-appointment-warning">
                        <h6 class="text-warning mb-2">
                            <i class="fas fa-clock me-2"></i>Past Appointment Notice
                        </h6>
                        <p class="mb-0">This appointment was scheduled for a past date. Cancelling past appointments should only be done for administrative purposes.</p>
                    </div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-times me-2"></i>Appointment Cancellation
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="appointment-details">
                            <div class="row detail-row">
                                <div class="col-md-2 detail-label">Appointment ID:</div>
                                <div class="col-md-4 detail-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></div>
                                <div class="col-md-2 detail-label">Current Status:</div>
                                <div class="col-md-4 detail-value">
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
                                        <?php echo htmlspecialchars($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="row detail-row">
                                <div class="col-md-2 detail-label">Patient:</div>
                                <div class="col-md-4 detail-value">
                                    <a href="admin_patient_view.php?id=<?php echo $appointment['patient_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                    </a>
                                </div>
                                <div class="col-md-2 detail-label">Doctor:</div>
                                <div class="col-md-4 detail-value">
                                    <a href="admin_doctor_view.php?id=<?php echo $appointment['doctor_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="row detail-row">
                                <div class="col-md-2 detail-label">Date & Time:</div>
                                <div class="col-md-4 detail-value">
                                    <?php 
                                        $date = new DateTime($appointment['appointment_datetime']);
                                        echo $date->format('M d, Y h:i A'); 
                                        if ($is_past_appointment) {
                                            echo ' <span class="text-muted">(Past)</span>';
                                        }
                                    ?>
                                </div>
                                <div class="col-md-2 detail-label">Reason:</div>
                                <div class="col-md-4 detail-value"><?php echo htmlspecialchars($appointment['reason_for_visit']); ?></div>
                            </div>
                            <?php if (!empty($appointment['created_at'])): ?>
                            <div class="row detail-row">
                                <div class="col-md-2 detail-label">Created:</div>
                                <div class="col-md-4 detail-value">
                                    <?php 
                                        $created_date = new DateTime($appointment['created_at']);
                                        echo $created_date->format('M d, Y h:i A'); 
                                    ?>
                                </div>
                                <?php if (!empty($appointment['updated_at'])): ?>
                                <div class="col-md-2 detail-label">Last Updated:</div>
                                <div class="col-md-4 detail-value">
                                    <?php 
                                        $updated_date = new DateTime($appointment['updated_at']);
                                        echo $updated_date->format('M d, Y h:i A'); 
                                    ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="confirmation-box bg-light">
                            <h5 class="text-danger mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>Confirm Cancellation
                            </h5>
                            <div class="alert alert-warning" role="alert">
                                <h6 class="alert-heading mb-2">Important Notice:</h6>
                                <ul class="mb-0">
                                    <li>This action will permanently change the appointment status to "Cancelled"</li>
                                    <li>The patient and doctor should be notified separately about this cancellation</li>
                                    <li>This action will be logged in the admin activity log</li>
                                    <li>This action cannot be undone automatically</li>
                                </ul>
                            </div>
                            
                            <form method="post" id="cancellationForm">
                                <div class="mb-4">
                                    <label for="cancellation_reason" class="form-label">
                                        <strong>Cancellation Reason</strong>
                                        <span class="text-muted">(Optional but recommended)</span>
                                    </label>
                                    <textarea 
                                        class="form-control" 
                                        id="cancellation_reason" 
                                        name="cancellation_reason" 
                                        rows="4" 
                                        placeholder="Please provide a reason for this cancellation (e.g., doctor unavailable, patient request, emergency, etc.)..."
                                        maxlength="500"
                                    ></textarea>
                                    <div class="form-text">
                                        <span id="charCount">0</span>/500 characters
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <a href="admin_appointment_view.php?id=<?php echo $appointment_id; ?>" class="btn btn-outline-secondary me-3">
                                        <i class="fas fa-times me-1"></i> No, Keep Appointment
                                    </a>
                                    <button type="submit" class="btn btn-danger" id="confirmBtn">
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
    <script>
        // Character counter for cancellation reason
        document.getElementById('cancellation_reason').addEventListener('input', function() {
            const charCount = this.value.length;
            document.getElementById('charCount').textContent = charCount;
            
            // Change color when approaching limit
            const countElement = document.getElementById('charCount');
            if (charCount > 450) {
                countElement.className = 'text-danger fw-bold';
            } else if (charCount > 400) {
                countElement.className = 'text-warning fw-bold';
            } else {
                countElement.className = '';
            }
        });

        // Confirmation dialog
        document.getElementById('cancellationForm').addEventListener('submit', function(e) {
            const reason = document.getElementById('cancellation_reason').value.trim();
            let confirmMessage = 'Are you absolutely sure you want to cancel this appointment?';
            
            if (reason === '') {
                confirmMessage += '\n\nNote: You have not provided a cancellation reason.';
            }
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>