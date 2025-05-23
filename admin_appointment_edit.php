<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_users'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connection.php';

// Get appointment ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_appointments.php");
    exit;
}

$appointment_id = $_GET['id'];

// Fetch appointment details
$appointment_query = "SELECT a.*, 
                      CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_id,
                      CONCAT(d.first_name, ' ', d.last_name) as doctor_name, d.doctor_id
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN doctors d ON a.doctor_id = d.doctor_id
                      WHERE a.appointment_id = ?";
$stmt = $conn->prepare($appointment_query);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    header("Location: admin_appointments.php");
    exit;
}

// Fetch all doctors for dropdown
$doctors_query = "SELECT doctor_id, CONCAT(first_name, ' ', last_name) as doctor_name FROM doctors ORDER BY last_name";
$doctors_result = $conn->query($doctors_query);
$doctors = $doctors_result->fetch_all(MYSQLI_ASSOC);

// Fetch all patients for dropdown
$patients_query = "SELECT patient_id, CONCAT(first_name, ' ', last_name) as patient_name FROM patients ORDER BY last_name";
$patients_result = $conn->query($patients_query);
$patients = $patients_result->fetch_all(MYSQLI_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $appointment_datetime = $_POST['appointment_datetime'];
    $reason_for_visit = $_POST['reason_for_visit'];
    $status = $_POST['status'];
    
    // Update appointment
    $update_query = "UPDATE appointments SET 
                    patient_id = ?, 
                    doctor_id = ?, 
                    appointment_datetime = ?, 
                    reason_for_visit = ?, 
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP()
                    WHERE appointment_id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iisssi", $patient_id, $doctor_id, $appointment_datetime, $reason_for_visit, $status, $appointment_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Appointment updated successfully!";
        header("Location: admin_appointment_view.php?id=" . $appointment_id);
        exit;
    } else {
        $error_message = "Error updating appointment: " . $conn->error;
    }
}

// Get admin information for header
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
    <title>Edit Appointment - TimeSync Admin</title>
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
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        .status-scheduled {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-completed {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .form-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        .form-section h5 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
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
                    <h1 class="h2">Edit Appointment</h1>
                    <div class="d-flex align-items-center">
                        <a href="admin_appointments.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left me-1"></i> Back to Appointments
                        </a>
                        <div class="dropdown">
                            <a href="?toggle_menu=user" class="btn btn-outline-secondary">
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

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="form-section">
                    <form method="POST" action="">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Appointment Details</h5>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                    <?php echo htmlspecialchars($appointment['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="patient_id" class="form-label">Patient</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['patient_id']; ?>" <?php echo $patient['patient_id'] == $appointment['patient_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($patient['patient_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="doctor_id" class="form-label">Doctor</label>
                                <select class="form-select" id="doctor_id" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['doctor_id']; ?>" <?php echo $doctor['doctor_id'] == $appointment['doctor_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="appointment_datetime" class="form-label">Date & Time</label>
                                <input type="datetime-local" class="form-control" id="appointment_datetime" name="appointment_datetime" 
                                    value="<?php echo date('Y-m-d\TH:i', strtotime($appointment['appointment_datetime'])); ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Scheduled" <?php echo $appointment['status'] == 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="Confirmed" <?php echo $appointment['status'] == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="Cancelled" <?php echo $appointment['status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="Completed" <?php echo $appointment['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label for="reason_for_visit" class="form-label">Reason for Visit</label>
                                <textarea class="form-control" id="reason_for_visit" name="reason_for_visit" rows="3"><?php echo htmlspecialchars($appointment['reason_for_visit']); ?></textarea>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                            <a href="admin_appointment_view.php?id=<?php echo $appointment_id; ?>" class="btn btn-outline-secondary">
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
        // Simple dropdown toggle functionality
        document.querySelector('.dropdown > a').addEventListener('click', function(e) {
            e.preventDefault();
            const menu = this.nextElementSibling;
            menu.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
    </script>
</body>
</html>