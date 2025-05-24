<?php
session_start();

// Check if admin is logged in, if not redirect to login page
if (!isset($_SESSION['admin_users']) || empty($_SESSION['admin_users'])) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get user type and ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_type = isset($_GET['type']) ? $_GET['type'] : '';

// Validate user type
if (!in_array($user_type, ['patient', 'doctor', 'admin'])) {
    die("Invalid user type");
}

// Determine table and fields based on user type
if ($user_type == 'doctor') {
    $table = 'doctor_users';
    $fields = ['name', 'username', 'email', 'phone', 'department', 'created_at'];
} elseif ($user_type == 'admin') {
    $table = 'admin_users';
    $fields = ['name', 'username', 'email', 'phone', 'created_at'];
} else {
    $table = 'patient_users';
    $fields = ['name', 'username', 'email', 'phone', 'created_at'];
}

// Get user data
$query = "SELECT * FROM $table WHERE id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die("Error getting result: " . $stmt->error);
}

$user_data = $result->fetch_assoc();
if (!$user_data) {
    die("User not found");
}

// Get admin information for header
$admin_id = $_SESSION['admin_users'];
$admin_query = "SELECT name as full_name FROM admin_users WHERE id = ?";
$admin_stmt = $conn->prepare($admin_query);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$admin_data = $admin_result->fetch_assoc();

// Function to get user initials
function getUserInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Function to format date
function formatDate($dateString) {
    try {
        $date = new DateTime($dateString);
        return $date->format('M d, Y');
    } catch (Exception $e) {
        return 'N/A';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - TimeSync</title>
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
            font-size: 2rem;
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

        /* User details card */
        .user-details-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .detail-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #6c757d;
        }

        .detail-value {
            color: #212529;
        }

        .back-button {
            margin-bottom: 1.5rem;
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
                    <h1 class="h2">User Details</h1>
                    <div class="dropdown">
                        <a href="#" class="btn btn-outline-secondary">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                        </a>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <a href="admin_users.php?tab=<?php echo $user_type == 'doctor' ? 'doctors' : ($user_type == 'admin' ? 'admins' : 'patients'); ?>" class="btn btn-outline-secondary back-button">
                            <i class="fas fa-arrow-left me-1"></i> Back to Users
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card user-details-card h-100">
                            <div class="card-body text-center">
                                <div class="user-avatar mb-3">
                                    <?php echo getUserInitials($user_data['name']); ?>
                                </div>
                                <h4><?php echo htmlspecialchars($user_data['name']); ?></h4>
                                <span class="badge <?php echo $user_type == 'doctor' ? 'doctor-badge' : ($user_type == 'admin' ? 'admin-badge' : 'patient-badge'); ?>">
                                    <?php echo ucfirst($user_type); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card user-details-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">User Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="detail-item">
                                    <div class="row">
                                        <div class="col-md-3 detail-label">Username</div>
                                        <div class="col-md-9 detail-value"><?php echo htmlspecialchars($user_data['username']); ?></div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="row">
                                        <div class="col-md-3 detail-label">Email</div>
                                        <div class="col-md-9 detail-value"><?php echo htmlspecialchars($user_data['email']); ?></div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="row">
                                        <div class="col-md-3 detail-label">Phone</div>
                                        <div class="col-md-9 detail-value"><?php echo htmlspecialchars($user_data['phone'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <?php if ($user_type == 'doctor'): ?>
                                <div class="detail-item">
                                    <div class="row">
                                        <div class="col-md-3 detail-label">Department</div>
                                        <div class="col-md-9 detail-value"><?php echo htmlspecialchars($user_data['department'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <div class="row">
                                        <div class="col-md-3 detail-label">Registered On</div>
                                        <div class="col-md-9 detail-value"><?php echo formatDate($user_data['created_at']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card user-details-card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex gap-2">
                                    <a href="admin_edit_user.php?id=<?php echo $user_id; ?>&type=<?php echo $user_type; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit me-1"></i> Edit User
                                    </a>
                                    <?php if ($user_id != $admin_id): ?>
                                    <a href="admin_delete_user.php?id=<?php echo $user_id; ?>&type=<?php echo $user_type; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">
                                        <i class="fas fa-trash-alt me-1"></i> Delete User
                                    </a>
                                    <?php endif; ?>
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

<?php
// Close database connections
$stmt->close();
$admin_stmt->close();
$conn->close();
?>