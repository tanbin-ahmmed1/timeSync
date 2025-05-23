<?php
session_start();

// Check if admin is logged in, if not redirect to login page
if (!isset($_SESSION['admin_users']) || empty($_SESSION['admin_users'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'db_connection.php';

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

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_system_settings':
                // Update system settings
                $site_name = $_POST['site_name'] ?? 'TimeSync';
                $admin_email = $_POST['admin_email'] ?? '';
                $appointment_duration = $_POST['appointment_duration'] ?? '30';
                $max_advance_booking = $_POST['max_advance_booking'] ?? '30';
                $working_hours_start = $_POST['working_hours_start'] ?? '09:00';
                $working_hours_end = $_POST['working_hours_end'] ?? '17:00';
                $timezone = $_POST['timezone'] ?? 'UTC';
                
                try {
                    // Update or insert system settings
                    $settings = [
                        'site_name' => $site_name,
                        'admin_email' => $admin_email,
                        'appointment_duration' => $appointment_duration,
                        'max_advance_booking' => $max_advance_booking,
                        'working_hours_start' => $working_hours_start,
                        'working_hours_end' => $working_hours_end,
                        'timezone' => $timezone
                    ];
                    
                    foreach ($settings as $setting_name => $setting_value) {
                        $check_query = "SELECT setting_id FROM system_settings WHERE setting_name = ?";
                        $check_stmt = $conn->prepare($check_query);
                        $check_stmt->bind_param("s", $setting_name);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            // Update existing setting
                            $update_query = "UPDATE system_settings SET setting_value = ?, modified_by = ?, last_modified = NOW() WHERE setting_name = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bind_param("sis", $setting_value, $admin_id, $setting_name);
                            $update_stmt->execute();
                        } else {
                            // Insert new setting
                            $insert_query = "INSERT INTO system_settings (setting_name, setting_value, modified_by, setting_description) VALUES (?, ?, ?, ?)";
                            $description = ucwords(str_replace('_', ' ', $setting_name));
                            $insert_stmt = $conn->prepare($insert_query);
                            $insert_stmt->bind_param("ssis", $setting_name, $setting_value, $admin_id, $description);
                            $insert_stmt->execute();
                        }
                    }
                    
                    $message = "System settings updated successfully!";
                    $message_type = "success";
                } catch (Exception $e) {
                    $message = "Error updating system settings: " . $e->getMessage();
                    $message_type = "danger";
                }
                break;
                
            case 'update_profile':
                // Update admin profile
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                $phone = $_POST['phone'] ?? '';
                
                try {
                    $update_query = "UPDATE admin_users SET name = ?, email = ?, phone = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("sssi", $name, $email, $phone, $admin_id);
                    $stmt->execute();
                    
                    $message = "Profile updated successfully!";
                    $message_type = "success";
                    
                    // Refresh admin data
                    $admin_data['full_name'] = $name;
                } catch (Exception $e) {
                    $message = "Error updating profile: " . $e->getMessage();
                    $message_type = "danger";
                }
                break;
                
            case 'change_password':
                // Change password
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if ($new_password !== $confirm_password) {
                    $message = "New passwords do not match!";
                    $message_type = "danger";
                } else {
                    try {
                        // Verify current password
                        $verify_query = "SELECT password FROM admin_users WHERE id = ?";
                        $verify_stmt = $conn->prepare($verify_query);
                        $verify_stmt->bind_param("i", $admin_id);
                        $verify_stmt->execute();
                        $verify_result = $verify_stmt->get_result();
                        $admin_password = $verify_result->fetch_assoc();
                        
                        if (password_verify($current_password, $admin_password['password'])) {
                            // Update password
                            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_query = "UPDATE admin_users SET password = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bind_param("si", $new_password_hash, $admin_id);
                            $update_stmt->execute();
                            
                            $message = "Password changed successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Current password is incorrect!";
                            $message_type = "danger";
                        }
                    } catch (Exception $e) {
                        $message = "Error changing password: " . $e->getMessage();
                        $message_type = "danger";
                    }
                }
                break;
        }
    }
}

// Get current system settings
$settings_query = "SELECT setting_name, setting_value FROM system_settings";
$settings_result = $conn->query($settings_query);
$current_settings = [];
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        $current_settings[$row['setting_name']] = $row['setting_value'];
    }
}

// Get current admin profile data
$profile_query = "SELECT name, email, phone FROM admin_users WHERE id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param("i", $admin_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile_data = $profile_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - TimeSync Admin</title>
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
        
        .settings-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .settings-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
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
                            <a class="nav-link active" href="admin_settings.php">
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
                    <h1 class="h2">Settings</h1>
                    <div class="dropdown">
                        <a href="?toggle_menu=user" class="btn btn-outline-secondary <?php echo $showUserMenu ? 'active' : ''; ?>">
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
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Settings Content -->
                <div class="row">
                    <!-- System Settings -->
                    <div class="col-lg-8">
                        <div class="card settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>System Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_system_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="site_name" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" 
                                                   value="<?php echo htmlspecialchars($current_settings['site_name'] ?? 'TimeSync'); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_email" class="form-label">Admin Email</label>
                                            <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                                   value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="appointment_duration" class="form-label">Default Appointment Duration (minutes)</label>
                                            <select class="form-select" id="appointment_duration" name="appointment_duration">
                                                <option value="15" <?php echo ($current_settings['appointment_duration'] ?? '30') == '15' ? 'selected' : ''; ?>>15 minutes</option>
                                                <option value="30" <?php echo ($current_settings['appointment_duration'] ?? '30') == '30' ? 'selected' : ''; ?>>30 minutes</option>
                                                <option value="45" <?php echo ($current_settings['appointment_duration'] ?? '30') == '45' ? 'selected' : ''; ?>>45 minutes</option>
                                                <option value="60" <?php echo ($current_settings['appointment_duration'] ?? '30') == '60' ? 'selected' : ''; ?>>60 minutes</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="max_advance_booking" class="form-label">Max Advance Booking (days)</label>
                                            <input type="number" class="form-control" id="max_advance_booking" name="max_advance_booking" 
                                                   value="<?php echo htmlspecialchars($current_settings['max_advance_booking'] ?? '30'); ?>" min="1" max="365">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="working_hours_start" class="form-label">Working Hours Start</label>
                                            <input type="time" class="form-control" id="working_hours_start" name="working_hours_start" 
                                                   value="<?php echo htmlspecialchars($current_settings['working_hours_start'] ?? '09:00'); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="working_hours_end" class="form-label">Working Hours End</label>
                                            <input type="time" class="form-control" id="working_hours_end" name="working_hours_end" 
                                                   value="<?php echo htmlspecialchars($current_settings['working_hours_end'] ?? '17:00'); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="timezone" class="form-label">System Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <option value="UTC" <?php echo ($current_settings['timezone'] ?? 'UTC') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            <option value="America/New_York" <?php echo ($current_settings['timezone'] ?? 'UTC') == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                            <option value="America/Chicago" <?php echo ($current_settings['timezone'] ?? 'UTC') == 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                            <option value="America/Denver" <?php echo ($current_settings['timezone'] ?? 'UTC') == 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                            <option value="America/Los_Angeles" <?php echo ($current_settings['timezone'] ?? 'UTC') == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                            <option value="Europe/London" <?php echo ($current_settings['timezone'] ?? 'UTC') == 'Europe/London' ? 'selected' : ''; ?>>London</option>
                                            <option value="Asia/Dhaka" <?php echo ($current_settings['timezone'] ?? 'UTC') == 'Asia/Dhaka' ? 'selected' : ''; ?>>Dhaka</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save System Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Settings -->
                    <div class="col-lg-4">
                        <div class="card settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($profile_data['name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($profile_data['email'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($profile_data['phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="card settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="row">
                    <div class="col-12">
                        <div class="card settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>TimeSync Version:</strong><br>
                                        <span class="text-muted">v1.0.0</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>PHP Version:</strong><br>
                                        <span class="text-muted"><?php echo phpversion(); ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Database:</strong><br>
                                        <span class="text-muted">MySQL <?php echo $conn->server_info; ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Server Time:</strong><br>
                                        <span class="text-muted"><?php echo date('Y-m-d H:i:s'); ?></span>
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
</body>
</html>