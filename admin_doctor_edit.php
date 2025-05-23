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
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_doctors.php");
    exit;
}

$doctor_id = (int)$_GET['id'];
$message = '';
$message_type = '';

// Get doctor information
$doctor_query = "SELECT * FROM doctor_users WHERE id = ?";
$stmt = $conn->prepare($doctor_query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: admin_doctors.php");
    exit;
}

$doctor_data = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone is required";
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (empty($department)) {
        $errors[] = "Department is required";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    }
    
    // Check if username is already taken by another doctor
    if (!empty($username)) {
        $check_username = "SELECT id FROM doctor_users WHERE username = ? AND id != ?";
        $stmt = $conn->prepare($check_username);
        $stmt->bind_param("si", $username, $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Username is already taken";
        }
    }
    
    // Check if email is already taken by another doctor
    if (!empty($email)) {
        $check_email = "SELECT id FROM doctor_users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($check_email);
        $stmt->bind_param("si", $email, $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email is already registered";
        }
    }
    
    // Password validation (only if provided)
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if (empty($errors)) {
        // Update doctor information
        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_query = "UPDATE doctor_users SET name = ?, email = ?, phone = ?, department = ?, username = ?, password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssssi", $name, $email, $phone, $department, $username, $hashed_password, $doctor_id);
        } else {
            // Update without changing password
            $update_query = "UPDATE doctor_users SET name = ?, email = ?, phone = ?, department = ?, username = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssssi", $name, $email, $phone, $department, $username, $doctor_id);
        }
        
        if ($stmt->execute()) {
            // Log admin activity
            $action_details = "updated doctor ID: $doctor_id (Name: $name)";
            $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) VALUES (?, 'UPDATE', ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param("is", $admin_id, $action_details);
            $log_stmt->execute();
            
            $message = "Doctor information updated successfully.";
            $message_type = "success";
            
            // Refresh doctor data
            $doctor_query = "SELECT * FROM doctor_users WHERE id = ?";
            $stmt = $conn->prepare($doctor_query);
            $stmt->bind_param("i", $doctor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $doctor_data = $result->fetch_assoc();
        } else {
            $message = "Error updating doctor information: " . $conn->error;
            $message_type = "danger";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "danger";
    }
}

// Get all departments for dropdown
$departments_query = "SELECT DISTINCT department FROM doctor_users WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Add common departments if not already in the list
$common_departments = ['General Medicine', 'Cardiology', 'Neurology', 'Orthopedics', 'Pediatrics', 'Dermatology', 'Psychiatry', 'Surgery', 'Gynecology', 'ENT'];
foreach ($common_departments as $dept) {
    if (!in_array($dept, $departments)) {
        $departments[] = $dept;
    }
}
sort($departments);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor - TimeSync Admin</title>
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
        
        .form-card {
            border-left: 4px solid #007bff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }
        
        .password-field {
            position: relative;
        }
        
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
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
                        <h1 class="h2">Edit Doctor</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="admin_doctors.php">Doctors</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Edit Doctor</li>
                            </ol>
                        </nav>
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

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Doctor Info Card -->
                    <div class="col-md-4 mb-4">
                        <div class="card info-card h-100">
                            <div class="card-body">
                                <div class="text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-user-md fa-4x mb-3"></i>
                                    </div>
                                    <h4>Dr. <?php echo htmlspecialchars($doctor_data['name']); ?></h4>
                                    <p class="mb-1"><?php echo htmlspecialchars($doctor_data['department']); ?></p>
                                    <p class="mb-3"><?php echo htmlspecialchars($doctor_data['email']); ?></p>
                                    <small>
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Joined <?php echo date('M d, Y', strtotime($doctor_data['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Form -->
                    <div class="col-md-8">
                        <div class="card form-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit me-2"></i>Edit Doctor Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">
                                                Full Name <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($doctor_data['name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="username" class="form-label">
                                                Username <span class="required">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   value="<?php echo htmlspecialchars($doctor_data['username']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">
                                                Email Address <span class="required">*</span>
                                            </label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($doctor_data['email']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">
                                                Phone Number <span class="required">*</span>
                                            </label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($doctor_data['phone']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="department" class="form-label">
                                            Department <span class="required">*</span>
                                        </label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="">Select Department</option>
                                            <?php foreach($departments as $department): ?>
                                                <option value="<?php echo htmlspecialchars($department); ?>" 
                                                        <?php echo ($doctor_data['department'] == $department) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($department); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            New Password <small class="text-muted">(Leave blank to keep current password)</small>
                                        </label>
                                        <div class="password-field">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Enter new password (optional)">
                                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                                        </div>
                                        <small class="form-text text-muted">
                                            Password must be at least 6 characters long (if provided).
                                        </small>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="admin_doctors.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Doctors
                                        </a>
                                        <div>
                                            <button type="reset" class="btn btn-outline-warning me-2">
                                                <i class="fas fa-undo me-2"></i>Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Update Doctor
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Additional Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Doctor ID:</strong><br>
                                        <span class="text-muted">#<?php echo $doctor_data['id']; ?></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Registration Date:</strong><br>
                                        <span class="text-muted"><?php echo date('F d, Y \a\t g:i A', strtotime($doctor_data['created_at'])); ?></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Last Updated:</strong><br>
                                        <span class="text-muted">
                                            <?php 
                                            if (isset($doctor_data['updated_at']) && !empty($doctor_data['updated_at'])) {
                                                echo date('F d, Y \a\t g:i A', strtotime($doctor_data['updated_at']));
                                            } else {
                                                echo "Never";
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const department = document.getElementById('department').value;
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            let errors = [];

            if (!name) errors.push('Name is required');
            if (!email) errors.push('Email is required');
            if (!phone) errors.push('Phone is required');
            if (!department) errors.push('Department is required');
            if (!username) errors.push('Username is required');
            
            if (username && username.length < 3) {
                errors.push('Username must be at least 3 characters long');
            }
            
            if (password && password.length < 6) {
                errors.push('Password must be at least 6 characters long');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
            }
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.slice(0, 3) + '-' + value.slice(3);
                } else {
                    value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6, 10);
                }
            }
            e.target.value = value;
        });
    </script>
</body>
</html>