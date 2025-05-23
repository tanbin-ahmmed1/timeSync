<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_users'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connection.php';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

// Get list of doctors and patients for dropdowns (using correct table names)
$doctors_query = "SELECT id as doctor_id, name as doctor_name, department FROM doctor_users ORDER BY name";
$doctors = $conn->query($doctors_query);

$patients_query = "SELECT id as patient_id, name as patient_name, email FROM patient_users ORDER BY name";
$patients = $conn->query($patients_query);

// Function to check for appointment conflicts
function checkAppointmentConflict($conn, $doctor_id, $appointment_datetime, $duration_minutes = 30) {
    $start_time = $appointment_datetime;
    $end_time = date('Y-m-d H:i:s', strtotime($appointment_datetime . ' +' . $duration_minutes . ' minutes'));
    
    $conflict_query = "SELECT COUNT(*) as conflicts FROM appointments 
                      WHERE doctor_id = ? 
                      AND status NOT IN ('Cancelled', 'Completed')
                      AND (
                          (appointment_datetime <= ? AND DATE_ADD(appointment_datetime, INTERVAL 30 MINUTE) > ?) OR
                          (appointment_datetime < ? AND DATE_ADD(appointment_datetime, INTERVAL 30 MINUTE) >= ?)
                      )";
    
    $stmt = $conn->prepare($conflict_query);
    $stmt->bind_param("issss", $doctor_id, $start_time, $start_time, $end_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['conflicts'] > 0;
}

// Function to validate business hours
function isWithinBusinessHours($datetime) {
    $day_of_week = date('N', strtotime($datetime)); // 1 (Monday) to 7 (Sunday)
    $hour = date('H', strtotime($datetime));
    
    // Monday to Friday: 8 AM to 6 PM, Saturday: 9 AM to 2 PM, Sunday: Closed
    if ($day_of_week >= 1 && $day_of_week <= 5) {
        return $hour >= 8 && $hour < 18;
    } elseif ($day_of_week == 6) {
        return $hour >= 9 && $hour < 14;
    } else {
        return false; // Sunday closed
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Security token mismatch. Please try again.";
    } else {
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        $appointment_datetime = $_POST['appointment_datetime'];
        $reason = trim($_POST['reason']);
        $status = $_POST['status'];
        
        $errors = [];
        
        // Validate required fields
        if (!$patient_id) {
            $errors[] = "Please select a valid patient.";
        }
        if (!$doctor_id) {
            $errors[] = "Please select a valid doctor.";
        }
        if (empty($appointment_datetime)) {
            $errors[] = "Please select appointment date and time.";
        }
        
        // Validate appointment date/time
        if (!empty($appointment_datetime)) {
            $appointment_timestamp = strtotime($appointment_datetime);
            if ($appointment_timestamp === false) {
                $errors[] = "Invalid date and time format.";
            } elseif ($appointment_timestamp <= time()) {
                $errors[] = "Appointment must be scheduled for a future date and time.";
            } elseif (!isWithinBusinessHours($appointment_datetime)) {
                $errors[] = "Appointments can only be scheduled during business hours: Monday-Friday (8 AM - 6 PM), Saturday (9 AM - 2 PM).";
            }
        }
        
        // Check for conflicts if basic validation passes
        if (empty($errors) && $doctor_id && !empty($appointment_datetime)) {
            if (checkAppointmentConflict($conn, $doctor_id, $appointment_datetime)) {
                $errors[] = "The selected time slot conflicts with an existing appointment. Please choose a different time.";
            }
        }
        
        // Validate status
        $valid_statuses = ['Scheduled', 'Confirmed', 'Cancelled', 'Completed'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'Scheduled'; // Default fallback
        }
        
        // If no errors, proceed with insertion
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                $insert_query = "INSERT INTO appointments (patient_id, doctor_id, appointment_datetime, reason_for_visit, status) 
                                VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("iisss", $patient_id, $doctor_id, $appointment_datetime, $reason, $status);
                
                if ($stmt->execute()) {
                    $appointment_id = $conn->insert_id;
                    
                    // Log admin activity
                    $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) 
                                 VALUES (?, 'appointment_create', ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_details = "Created appointment ID: $appointment_id for patient ID: $patient_id with doctor ID: $doctor_id";
                    $log_stmt->bind_param("is", $admin_id, $log_details);
                    $log_stmt->execute();
                    
                    $conn->commit();
                    $success_message = "Appointment successfully created! (ID: #$appointment_id)";
                    
                    // Clear form data
                    $_POST = array();
                } else {
                    throw new Exception("Failed to create appointment: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error creating appointment: " . $e->getMessage();
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}

// Generate new CSRF token for the form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Appointment - TimeSync Admin</title>
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

        .form-section {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .required-field:after {
            content: " *";
            color: #dc3545;
        }
        
        .business-hours-info {
            background-color: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        .conflict-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
            display: none;
        }
        
        .form-control:invalid {
            border-color: #dc3545;
        }
        
        .form-control:valid {
            border-color: #198754;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
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
                    <h1 class="h2">Add New Appointment</h1>
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

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="form-section">
                    <div class="form-header">
                        <h4><i class="fas fa-calendar-plus me-2"></i> Appointment Details</h4>
                        <p class="text-muted mb-0">Fill in the information below to schedule a new appointment</p>
                    </div>
                    
                    <form method="POST" action="admin_appointment_add.php" id="appointmentForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="patient_id" class="form-label required-field">Patient</label>
                                    <select class="form-select" id="patient_id" name="patient_id" required>
                                        <option value="">Select Patient</option>
                                        <?php if ($patients && $patients->num_rows > 0): ?>
                                            <?php $patients->data_seek(0); while ($patient = $patients->fetch_assoc()): ?>
                                                <option value="<?php echo $patient['patient_id']; ?>" 
                                                        data-email="<?php echo htmlspecialchars($patient['email']); ?>"
                                                        <?php echo (isset($_POST['patient_id']) && $_POST['patient_id'] == $patient['patient_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($patient['patient_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No patients available</option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a patient.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="doctor_id" class="form-label required-field">Doctor</label>
                                    <select class="form-select" id="doctor_id" name="doctor_id" required>
                                        <option value="">Select Doctor</option>
                                        <?php if ($doctors && $doctors->num_rows > 0): ?>
                                            <?php $doctors->data_seek(0); while ($doctor = $doctors->fetch_assoc()): ?>
                                                <option value="<?php echo $doctor['doctor_id']; ?>" 
                                                        data-department="<?php echo htmlspecialchars($doctor['department']); ?>"
                                                        <?php echo (isset($_POST['doctor_id']) && $_POST['doctor_id'] == $doctor['doctor_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                                                    <?php if ($doctor['department']): ?>
                                                        - <?php echo htmlspecialchars($doctor['department']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No doctors available</option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a doctor.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="Scheduled" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Scheduled') || !isset($_POST['status']) ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="Confirmed" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="Cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="Completed" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_datetime" class="form-label required-field">Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="appointment_datetime" name="appointment_datetime" 
                                           value="<?php echo isset($_POST['appointment_datetime']) ? htmlspecialchars($_POST['appointment_datetime']) : ''; ?>" 
                                           min="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>" required>
                                    <div class="invalid-feedback">Please select a valid future date and time.</div>
                                    <div class="business-hours-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <strong>Business Hours:</strong><br>
                                        Monday - Friday: 8:00 AM - 6:00 PM<br>
                                        Saturday: 9:00 AM - 2:00 PM<br>
                                        Sunday: Closed
                                    </div>
                                    <div class="conflict-warning" id="conflictWarning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <span id="conflictMessage"></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Reason for Visit</label>
                                    <textarea class="form-control" id="reason" name="reason" rows="4" 
                                              placeholder="Enter the reason for this appointment..."><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
                                    <div class="form-text">Optional: Provide details about the purpose of this appointment</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary me-2" id="submitBtn">
                                <i class="fas fa-save me-1"></i> 
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                Save Appointment
                            </button>
                            <a href="admin_appointments.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('appointmentForm');
            const appointmentDatetime = document.getElementById('appointment_datetime');
            const doctorSelect = document.getElementById('doctor_id');
            const conflictWarning = document.getElementById('conflictWarning');
            const submitBtn = document.getElementById('submitBtn');
            
            // Form validation
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                // Show loading state
                const spinner = submitBtn.querySelector('.spinner-border');
                spinner.classList.remove('d-none');
                submitBtn.disabled = true;
                
                form.classList.add('was-validated');
            });
            
            // Real-time date/time validation
            appointmentDatetime.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const now = new Date();
                
                // Check if date is in the past
                if (selectedDate <= now) {
                    this.setCustomValidity('Appointment must be scheduled for a future date and time.');
                    return;
                }
                
                // Check business hours
                const dayOfWeek = selectedDate.getDay(); // 0 = Sunday, 1 = Monday, etc.
                const hour = selectedDate.getHours();
                
                let isValidTime = false;
                
                if (dayOfWeek >= 1 && dayOfWeek <= 5) { // Monday to Friday
                    isValidTime = hour >= 8 && hour < 18;
                } else if (dayOfWeek === 6) { // Saturday
                    isValidTime = hour >= 9 && hour < 14;
                }
                
                if (!isValidTime) {
                    this.setCustomValidity('Appointments can only be scheduled during business hours.');
                } else {
                    this.setCustomValidity('');
                    // Check for conflicts if doctor is selected
                    if (doctorSelect.value) {
                        checkConflicts();
                    }
                }
            });
            
            // Check for appointment conflicts
            function checkConflicts() {
                if (!doctorSelect.value || !appointmentDatetime.value) {
                    conflictWarning.style.display = 'none';
                    return;
                }
                
                // This would typically be an AJAX call to check conflicts
                // For now, we'll just show a placeholder message
                // In a real implementation, you'd send a request to a PHP script
                // that checks the database for conflicts
                
                // Simulated conflict check (replace with actual AJAX call)
                setTimeout(() => {
                    // This is just for demonstration - replace with real conflict checking
                    const isWeekend = new Date(appointmentDatetime.value).getDay() === 0;
                    if (isWeekend) {
                        document.getElementById('conflictMessage').textContent = 'Note: Sunday appointments are not available.';
                        conflictWarning.style.display = 'block';
                    } else {
                        conflictWarning.style.display = 'none';
                    }
                }, 500);
            }
            
            doctorSelect.addEventListener('change', checkConflicts);
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>