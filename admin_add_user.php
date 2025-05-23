<?php
session_start();

// Check if admin is logged in, if not redirect to login page
if (!isset($_SESSION['admin_users']) || empty($_SESSION['admin_users'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get user type from URL parameter
$user_type = isset($_GET['type']) && in_array($_GET['type'], ['patient', 'doctor', 'admin']) ? $_GET['type'] : 'patient';

// Initialize variables
$name = $username = $email = $phone = $department = $password = $confirm_password = '';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate name
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    } elseif (strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters';
    }

    // Validate username
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($username) < 4) {
        $errors['username'] = 'Username must be at least 4 characters';
    } else {
        // Check if username exists in the appropriate table
        $table = $user_type . '_users';
        $check_username = $conn->prepare("SELECT id FROM $table WHERE username = ?");
        $check_username->bind_param("s", $username);
        $check_username->execute();
        $check_username->store_result();
        
        if ($check_username->num_rows > 0) {
            $errors['username'] = 'Username already exists';
        }
        $check_username->close();
    }

    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check if email exists in the appropriate table
        $table = $user_type . '_users';
        $check_email = $conn->prepare("SELECT id FROM $table WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();
        
        if ($check_email->num_rows > 0) {
            $errors['email'] = 'Email already exists';
        }
        $check_email->close();
    }

    // Validate phone if provided
    if (!empty($phone) && !preg_match('/^[0-9]{8,15}$/', $phone)) {
        $errors['phone'] = 'Invalid phone number format';
    }

    // Validate department for doctors
    if ($user_type === 'doctor' && empty($department)) {
        $errors['department'] = 'Department is required for doctors';
    }

    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }

    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    // If no errors, insert the new user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $table = $user_type . '_users';
        
        if ($user_type === 'doctor') {
            $query = "INSERT INTO $table (username, password, name, department, email, phone) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssss", $username, $hashed_password, $name, $department, $email, $phone);
        } else {
            $query = "INSERT INTO $table (username, password, name, email, phone) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssss", $username, $hashed_password, $name, $email, $phone);
        }

        if ($stmt->execute()) {
            $success = true;
            // Clear form fields
            $name = $username = $email = $phone = $department = $password = $confirm_password = '';
        } else {
            $errors['database'] = 'Error creating user: ' . $conn->error;
        }
        $stmt->close();
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
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add <?php echo ucfirst($user_type); ?> - TimeSync</title>
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

        /* Form styles */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .form-title {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .is-invalid {
            border-color: #dc3545;
        }
        
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        
        .was-validated .form-control:invalid ~ .invalid-feedback,
        .was-validated .form-control:invalid ~ .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback {
            display: block;
        }
        
        .success-message {
            display: none;
        }
        
        .show-success .success-message {
            display: block;
        }
        
        .show-success .form-container {
            display: none;
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
                    <h1 class="h2">Add New <?php echo ucfirst($user_type); ?></h1>
                    <div class="dropdown">
                        <a href="#" class="btn btn-outline-secondary">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                        </a>
                    </div>
                </div>

                <!-- Success Message -->
                <div class="alert alert-success success-message <?php echo $success ? 'd-block' : 'd-none'; ?>">
                    <h4><i class="fas fa-check-circle me-2"></i> Success!</h4>
                    <p>The <?php echo $user_type; ?> has been added successfully.</p>
                    <div class="mt-3">
                        <a href="admin_add_user.php?type=<?php echo $user_type; ?>" class="btn btn-outline-primary me-2">
                            <i class="fas fa-plus me-1"></i> Add Another
                        </a>
                        <a href="admin_users.php?tab=<?php echo $user_type . 's'; ?>" class="btn btn-primary">
                            <i class="fas fa-users me-1"></i> View All <?php echo ucfirst($user_type); ?>s
                        </a>
                    </div>
                </div>

                <!-- Form Container -->
                <div class="form-container <?php echo $success ? 'd-none' : 'd-block'; ?>">
                    <div class="form-title">
                        <h3><i class="fas fa-user-plus me-2"></i> <?php echo ucfirst($user_type); ?> Information</h3>
                    </div>
                    
                    <?php if (isset($errors['database'])): ?>
                        <div class="alert alert-danger">
                            <?php echo $errors['database']; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="admin_add_user.php?type=<?php echo $user_type; ?>" class="needs-validation <?php echo !empty($errors) ? 'was-validated' : ''; ?>" novalidate>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                       id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['name']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Please provide a valid name.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                       id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                                <?php if (isset($errors['username'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['username']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Please provide a valid username.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['email']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Please provide a valid email.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                       id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['phone']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($user_type === 'doctor'): ?>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['department']) ? 'is-invalid' : ''; ?>" 
                                        id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="General Medicine" <?php echo $department === 'General Medicine' ? 'selected' : ''; ?>>General Medicine</option>
                                    <option value="Cardiology" <?php echo $department === 'Cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                                    <option value="Neurology" <?php echo $department === 'Neurology' ? 'selected' : ''; ?>>Neurology</option>
                                    <option value="Pediatrics" <?php echo $department === 'Pediatrics' ? 'selected' : ''; ?>>Pediatrics</option>
                                    <option value="Orthopedics" <?php echo $department === 'Orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                                    <option value="Dermatology" <?php echo $department === 'Dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                                    <option value="Ophthalmology" <?php echo $department === 'Ophthalmology' ? 'selected' : ''; ?>>Ophthalmology</option>
                                </select>
                                <?php if (isset($errors['department'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['department']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Please select a department.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                       id="password" name="password" required>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['password']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Password must be at least 8 characters.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                       id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['confirm_password']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Passwords must match.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="admin_users.php?tab=<?php echo $user_type . 's'; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Users
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save <?php echo ucfirst($user_type); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Form validation
        (function () {
            'use strict'
            
            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.querySelectorAll('.needs-validation')
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>