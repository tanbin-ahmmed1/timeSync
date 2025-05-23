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

// Initialize variables
$message = '';
$message_type = '';
$doctor_data = null;
$appointment_count = 0;
$can_delete = false;

// Check if doctor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_doctors.php");
    exit;
}

$doctor_id = (int)$_GET['id'];

// Get doctor information
$doctor_query = "SELECT id, name, email, phone, department, created_at FROM doctor_users WHERE id = ?";
$stmt = $conn->prepare($doctor_query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor_data = $result->fetch_assoc();

// If doctor doesn't exist, redirect
if (!$doctor_data) {
    header("Location: admin_doctors.php");
    exit;
}

// Check if doctor has any appointments
$check_appointments = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?";
$stmt = $conn->prepare($check_appointments);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment_count = $result->fetch_assoc()['count'];

// Determine if doctor can be deleted (no appointments)
$can_delete = ($appointment_count == 0);

// Handle form submission for deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    if (!$can_delete) {
        $message = "Cannot delete doctor. They have $appointment_count appointments in the system.";
        $message_type = "danger";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete doctor user
            $delete_query = "DELETE FROM doctor_users WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $doctor_id);
            
            if ($stmt->execute()) {
                // Log admin activity
                $action_details = "Deleted doctor: " . $doctor_data['name'] . " (ID: $doctor_id, Email: " . $doctor_data['email'] . ")";
                $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) VALUES (?, 'DELETE', ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("is", $admin_id, $action_details);
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Redirect with success message
                $_SESSION['delete_success'] = "Doctor '" . $doctor_data['name'] . "' has been successfully deleted.";
                header("Location: admin_doctors.php");
                exit;
            } else {
                throw new Exception("Error deleting doctor: " . $conn->error);
            }
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $message = "Error deleting doctor: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Handle cancellation
if (isset($_POST['cancel'])) {
    header("Location: admin_doctors.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Doctor - TimeSync Admin</title>
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
        
        .delete-card {
            border-left: 4px solid #dc3545;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .doctor-info-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        
        .warning-card {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 0.375rem;
        }
        
        .danger-card {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 0.375rem;
        }
        
        .btn-danger-custom {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-danger-custom:hover {
            background-color: #c82333;
            border-color: #bd2130;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }
        
        .btn-secondary-custom {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-secondary-custom:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        
        .icon-large {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .confirmation-section {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            border-radius: 0.5rem;
            padding: 2rem;
            margin: 1.5rem 0;
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
                    <h1 class="h2">
                        <i class="fas fa-trash-alt text-danger me-2"></i>Delete Doctor
                    </h1>
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

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="admin_doctors.php">Doctors</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Delete Doctor</li>
                    </ol>
                </nav>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Delete Confirmation Card -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card delete-card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Doctor Deletion
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Warning Icon and Message -->
                                <div class="text-center mb-4">
                                    <i class="fas fa-exclamation-triangle text-danger icon-large"></i>
                                    <h4 class="text-danger">Warning: This action cannot be undone!</h4>
                                    <p class="text-muted">You are about to permanently delete the following doctor from the system.</p>
                                </div>

                                <!-- Doctor Information -->
                                <div class="doctor-info-card p-4 mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-user-md text-primary me-2"></i>Doctor Information
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($doctor_data['name']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor_data['email']); ?></p>
                                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($doctor_data['phone'] ?: 'Not provided'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Department:</strong> <?php echo htmlspecialchars($doctor_data['department'] ?: 'Not specified'); ?></p>
                                            <p><strong>Registration Date:</strong> 
                                                <?php 
                                                $date = new DateTime($doctor_data['created_at']);
                                                echo $date->format('F d, Y');
                                                ?>
                                            </p>
                                            <p><strong>Doctor ID:</strong> #<?php echo $doctor_id; ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Appointment Check -->
                                <?php if ($appointment_count > 0): ?>
                                    <div class="danger-card p-4 mb-4">
                                        <h5 class="text-danger mb-3">
                                            <i class="fas fa-ban me-2"></i>Cannot Delete Doctor
                                        </h5>
                                        <p class="mb-2">
                                            <strong>This doctor cannot be deleted because they have <?php echo $appointment_count; ?> appointment<?php echo ($appointment_count != 1) ? 's' : ''; ?> in the system.</strong>
                                        </p>
                                        <p class="mb-0">
                                            To delete this doctor, you must first:
                                        </p>
                                        <ul class="mt-2">
                                            <li>Cancel or reassign all existing appointments</li>
                                            <li>Ensure no future appointments are scheduled</li>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <div class="warning-card p-4 mb-4">
                                        <h5 class="text-warning mb-3">
                                            <i class="fas fa-check-circle me-2"></i>Safe to Delete
                                        </h5>
                                        <p class="mb-0">
                                            This doctor has no appointments in the system and can be safely deleted.
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <!-- Consequences Warning -->
                                <div class="confirmation-section">
                                    <h5 class="text-danger mb-3">
                                        <i class="fas fa-exclamation-circle me-2"></i>What will happen when you delete this doctor:
                                    </h5>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-times text-danger me-2"></i>
                                            The doctor's account will be permanently removed
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-times text-danger me-2"></i>
                                            All doctor profile information will be lost
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-times text-danger me-2"></i>
                                            The doctor will no longer be able to access the system
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-history text-info me-2"></i>
                                            This action will be logged in the admin activity log
                                        </li>
                                    </ul>
                                </div>

                                <!-- Action Form -->
                                <form method="POST" class="mt-4">
                                    <div class="d-flex justify-content-between flex-wrap">
                                        <button type="submit" name="cancel" class="btn btn-secondary-custom btn-lg">
                                            <i class="fas fa-arrow-left me-2"></i>Cancel & Go Back
                                        </button>
                                        
                                        <?php if ($can_delete): ?>
                                            <button type="submit" name="confirm_delete" class="btn btn-danger-custom btn-lg" 
                                                    id="deleteBtn" disabled>
                                                <i class="fas fa-trash-alt me-2"></i>Delete Doctor Permanently
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-danger btn-lg" disabled>
                                                <i class="fas fa-ban me-2"></i>Cannot Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>

                                <?php if ($can_delete): ?>
                                <!-- Confirmation Checkbox -->
                                <div class="mt-4 pt-3 border-top">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirmCheck">
                                        <label class="form-check-label text-danger fw-bold" for="confirmCheck">
                                            I understand that this action is permanent and cannot be undone. I want to delete Dr. <?php echo htmlspecialchars($doctor_data['name']); ?>.
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable delete button only when confirmation checkbox is checked
        <?php if ($can_delete): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const confirmCheck = document.getElementById('confirmCheck');
            const deleteBtn = document.getElementById('deleteBtn');
            
            confirmCheck.addEventListener('change', function() {
                deleteBtn.disabled = !this.checked;
            });
            
            // Add double-click confirmation for extra safety
            deleteBtn.addEventListener('click', function(e) {
                if (!confirm('Are you absolutely sure you want to delete this doctor? This action cannot be undone!')) {
                    e.preventDefault();
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>