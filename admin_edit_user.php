<?php
session_start();

// Check if admin is logged in, if not redirect to login page
if (!isset($_SESSION['admin_users']) || empty($_SESSION['admin_users'])) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get user ID and type from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_type = isset($_GET['type']) ? $_GET['type'] : '';

// Validate user type
if (!in_array($user_type, ['patient', 'doctor', 'admin'])) {
    header("Location: admin_users.php");
    exit;
}

// Get admin information
$admin_id = $_SESSION['admin_users'];
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);

if (!$stmt) {
    die("Error preparing admin query: " . $conn->error);
}

$stmt->bind_param("i", $admin_id);
if (!$stmt->execute()) {
    die("Error executing admin query: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die("Error getting admin result: " . $stmt->error);
}

$admin_data = $result->fetch_assoc();
if (!$admin_data) {
    die("Admin user not found");
}

// Determine table and fields based on user type
if ($user_type == 'patient') {
    $table = 'patient_users';
} elseif ($user_type == 'doctor') {
    $table = 'doctor_users';
} else {
    $table = 'admin_users';
}

// Get user data
$user_query = "SELECT * FROM $table WHERE id = ?";
$user_stmt = $conn->prepare($user_query);

if (!$user_stmt) {
    die("Error preparing user query: " . $conn->error);
}

$user_stmt->bind_param("i", $user_id);
if (!$user_stmt->execute()) {
    die("Error executing user query: " . $user_stmt->error);
}

$user_result = $user_stmt->get_result();
if (!$user_result) {
    die("Error getting user result: " . $user_stmt->error);
}

$user_data = $user_result->fetch_assoc();
if (!$user_data) {
    header("Location: admin_users.php?tab=" . $user_type . "s");
    exit;
}

// Process dropdown menu actions
$showUserMenu = false;
if (isset($_GET['toggle_menu']) && $_GET['toggle_menu'] == 'user') {
    $showUserMenu = true;
}

// Initialize variables
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = $user_type == 'doctor' ? trim($_POST['department']) : '';
    $password = trim($_POST['password']);
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if ($user_type == 'doctor' && empty($department)) {
        $errors[] = "Department is required for doctors";
    }
    
    // Check if username or email already exists (excluding current user)
    $check_query = "SELECT id FROM $table WHERE (username = ? OR email = ?) AND id != ?";
    $check_stmt = $conn->prepare($check_query);
    if ($check_stmt) {
        $check_stmt->bind_param("ssi", $username, $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $errors[] = "Username or email already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Build update query
        if ($user_type == 'doctor') {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_query = "UPDATE $table SET name = ?, username = ?, email = ?, phone = ?, department = ?, password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssssssi", $name, $username, $email, $phone, $department, $hashed_password, $user_id);
            } else {
                $update_query = "UPDATE $table SET name = ?, username = ?, email = ?, phone = ?, department = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssssi", $name, $username, $email, $phone, $department, $user_id);
            }
        } else {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_query = "UPDATE $table SET name = ?, username = ?, email = ?, phone = ?, password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssssi", $name, $username, $email, $phone, $hashed_password, $user_id);
            } else {
                $update_query = "UPDATE $table SET name = ?, username = ?, email = ?, phone = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssssi", $name, $username, $email, $phone, $user_id);
            }
        }
        
        if ($update_stmt && $update_stmt->execute()) {
            $success_message = ucfirst($user_type) . " updated successfully!";
            // Refresh user data
            $user_stmt = $conn->prepare($user_query);
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
        } else {
            $error_message = "Error updating " . $user_type . ": " . $conn->error;
        }
        
        if ($update_stmt) {
            $update_stmt->close();
        }
    } else {
        $error_message = implode(", ", $errors);
    }
}

// Function to get user initials
function getUserInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo ucfirst($user_type); ?> - TimeSync</title>
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

        /* User avatar styles */
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.5rem;
            margin: 0 auto;
        }

        /* Badge styles */
        .admin-badge {
            background-color: #f3e8ff;
            color: #7c3aed;
        }
        
        .doctor-badge {
            background-color: #e0f7fa;
            color: #00acc1;
        }
        
        .patient-badge {
            background-color: #e8f5e9;
            color: #43a047;
        }

        /* Form styles */
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .required {
            color: #dc3545;
        }

        /* Alert styles */
        .alert {
            border: none;
            border-radius: 0.5rem;
        }

        .alert-success {
            background-color: #d1edff;
            color: #0c5460;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Card styles */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }

        /* Button styles */
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #565e64;
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
                            <a class="nav-link text-dark" href="admin_appointments.php">
                                <i class="fas fa-calendar-check me-2"></i> Appointments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_users.php">
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
                    <div>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="admin_users.php">Users</a></li>
                                <li class="breadcrumb-item"><a href="admin_users.php?tab=<?php echo $user_type; ?>s"><?php echo ucfirst($user_type); ?>s</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Edit <?php echo ucfirst($user_type); ?></li>
                            </ol>
                        </nav>
                        <h1 class="h2">Edit <?php echo ucfirst($user_type); ?></h1>
                    </div>
                    <div class="dropdown">
                        <a href="?id=<?php echo $user_id; ?>&type=<?php echo $user_type; ?>&toggle_menu=user" 
                           class="btn btn-outline-secondary <?php echo $showUserMenu ? 'active' : ''; ?>">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu <?php echo $showUserMenu ? 'show' : ''; ?>" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Edit User Form -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit me-2"></i>
                                    Edit <?php echo ucfirst($user_type); ?> Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">
                                                    Full Name <span class="required">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?php echo htmlspecialchars($user_data['name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="username" class="form-label">
                                                    Username <span class="required">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="username" name="username" 
                                                       value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">
                                                    Email <span class="required">*</span>
                                                </label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($user_type == 'doctor'): ?>
                                    <div class="mb-3">
                                        <label for="department" class="form-label">
                                            Department <span class="required">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="department" name="department" 
                                               value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>" required>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            New Password
                                            <small class="text-muted">(Leave blank to keep current password)</small>
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Enter new password (optional)">
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="admin_users.php?tab=<?php echo $user_type; ?>s" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-1"></i> Back to <?php echo ucfirst($user_type); ?>s
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Update <?php echo ucfirst($user_type); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Info Sidebar -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    User Information
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="user-avatar mb-3">
                                    <?php echo getUserInitials($user_data['name']); ?>
                                </div>
                                <h5 class="mb-2"><?php echo htmlspecialchars($user_data['name']); ?></h5>
                                <span class="badge <?php echo $user_type == 'doctor' ? 'doctor-badge' : ($user_type == 'admin' ? 'admin-badge' : 'patient-badge'); ?> mb-3">
                                    <?php echo ucfirst($user_type); ?>
                                </span>
                                
                                <div class="text-start mt-3">
                                    <div class="mb-2">
                                        <small class="text-muted">Username:</small><br>
                                        <strong><?php echo htmlspecialchars($user_data['username']); ?></strong>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Email:</small><br>
                                        <strong><?php echo htmlspecialchars($user_data['email']); ?></strong>
                                    </div>
                                    <?php if (!empty($user_data['phone'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Phone:</small><br>
                                        <strong><?php echo htmlspecialchars($user_data['phone']); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($user_type == 'doctor' && !empty($user_data['department'])): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Department:</small><br>
                                        <strong><?php echo htmlspecialchars($user_data['department']); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Registered:</small><br>
                                        <strong><?php echo date('M d, Y', strtotime($user_data['created_at'])); ?></strong>
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
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            var dropdowns = document.querySelectorAll('.dropdown-menu');
            dropdowns.forEach(function(dropdown) {
                if (!dropdown.parentElement.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
<?php
// Close database connections
if ($stmt) $stmt->close();
if ($user_stmt) $user_stmt->close();
$conn->close();
?>