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
// Using admin_users table as per the database schema
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

// Check if page title is set, otherwise use default
$page_title = isset($page_title) ? $page_title . " - TimeSync" : "TimeSync Admin Panel";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="header_styles.css">
</head>
<body>
    <div class="container">
        <div class="wrapper">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <img src="TimeSync.png" alt="TimeSync Logo">
                    <h3>TimeSync Admin</h3>
                </div>
                <ul class="sidebar-menu">
                    <li>
                        <a class="<?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo ($current_page === 'doctors') ? 'active' : ''; ?>" href="admin_doctors.php">
                            <i class="fas fa-user-md"></i> Doctors
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo ($current_page === 'appointments') ? 'active' : ''; ?>" href="admin_appointments.php">
                            <i class="fas fa-calendar-check"></i> Appointments
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo ($current_page === 'users') ? 'active' : ''; ?>" href="admin_users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo ($current_page === 'system_stats') ? 'active' : ''; ?>" href="admin_system_stats.php">
                            <i class="fas fa-chart-line"></i> System Statistics
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo ($current_page === 'settings') ? 'active' : ''; ?>" href="admin_settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a class="<?php echo ($current_page === 'activity_logs') ? 'active' : ''; ?>" href="admin_activity_logs.php">
                            <i class="fas fa-history"></i> Activity Logs
                        </a>
                    </li>
                    <li class="sidebar-logout">
                        <a href="admin_logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main content -->
            <div class="main-content">
                <div class="header">
                    <h1><?php echo isset($page_header) ? $page_header : 'Dashboard'; ?></h1>
                    <div class="user-menu">
                        <div class="user-menu-header">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($admin_data['full_name']); ?></span>
                        </div>
                        <div class="user-menu-dropdown">
                            <a href="admin_profile.php"><i class="fas fa-user"></i> Profile</a>
                            <hr>
                            <a href="admin_logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div><!-- End of main-content -->
        </div><!-- End of wrapper -->
    </div><!-- End of container -->
</body>
</html>