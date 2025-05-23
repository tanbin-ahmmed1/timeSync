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

// Determine active tab (default to patients)
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['patients', 'doctors', 'admins']) ? $_GET['tab'] : 'patients';

// Handle search functionality
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Build the query based on active tab
$results_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $results_per_page;

if ($active_tab == 'patients') {
    $table = 'patient_users';
    $fields = ['name', 'email', 'username'];
} elseif ($active_tab == 'doctors') {
    $table = 'doctor_users';
    $fields = ['name', 'email', 'username', 'department'];
} else {
    $table = 'admin_users';
    $fields = ['name', 'email', 'username'];
}

// Build the base queries
$query = "SELECT * FROM $table";
$count_query = "SELECT COUNT(*) as total FROM $table";

// Add search conditions if needed
$search_params = [];
if (!empty($search)) {
    $search_conditions = [];
    foreach ($fields as $field) {
        $search_conditions[] = "$field LIKE ?";
        $search_params[] = "%$search%";
    }
    $where_clause = " WHERE " . implode(" OR ", $search_conditions);
    $query .= $where_clause;
    $count_query .= $where_clause;
}

$query .= " ORDER BY created_at DESC LIMIT ?, ?";

// Get total number of users
$count_stmt = $conn->prepare($count_query);
if (!$count_stmt) {
    die("Error preparing count query: " . $conn->error);
}

if (!empty($search)) {
    $types = str_repeat('s', count($search_params));
    $count_stmt->bind_param($types, ...$search_params);
}

if (!$count_stmt->execute()) {
    die("Error executing count query: " . $count_stmt->error);
}

$count_result = $count_stmt->get_result();
if (!$count_result) {
    die("Error getting count result: " . $count_stmt->error);
}

$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $results_per_page);

// Get users data with pagination
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing users query: " . $conn->error);
}

$bind_params = array_merge($search_params, [$offset, $results_per_page]);
if (!empty($search)) {
    $types = str_repeat('s', count($search_params)) . 'ii';
} else {
    $types = 'ii';
}

$stmt->bind_param($types, ...$bind_params);

if (!$stmt->execute()) {
    die("Error executing users query: " . $stmt->error);
}

$users_result = $stmt->get_result();
if (!$users_result) {
    die("Error getting users result: " . $stmt->error);
}

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
    <title>Manage Users - TimeSync</title>
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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

        /* Search box style */
        .search-box {
            max-width: 400px;
        }

        /* Tab styles */
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
        }
        
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom: 2px solid #0d6efd;
            background-color: transparent;
        }
        
        .nav-tabs .nav-link:hover:not(.active) {
            border-bottom: 2px solid #dee2e6;
        }

        /* Pagination styles */
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .page-link {
            color: #0d6efd;
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
                    <h1 class="h2">Manage Users</h1>
                    <div class="dropdown">
                        <a href="?toggle_menu=user<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&tab=<?php echo $active_tab; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>" 
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

                <!-- User Management Tools -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form class="d-flex" method="GET" action="admin_users.php">
                            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                            <div class="input-group search-box">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search <?php echo htmlspecialchars($active_tab); ?>..." 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       aria-label="Search users">
                                <button class="btn btn-outline-secondary" type="submit" aria-label="Search">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a href="admin_add_user.php?type=<?php echo $active_tab == 'doctors' ? 'doctor' : ($active_tab == 'admins' ? 'admin' : 'patient'); ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add New <?php echo ucfirst($active_tab == 'doctors' ? 'Doctor' : ($active_tab == 'admins' ? 'Admin' : 'Patient')); ?>
                        </a>
                    </div>
                </div>

                <!-- Users Tabs -->
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'patients' ? 'active' : ''; ?>" 
                           href="?tab=patients<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-user-injured me-1"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'doctors' ? 'active' : ''; ?>" 
                           href="?tab=doctors<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-user-md me-1"></i> Doctors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'admins' ? 'active' : ''; ?>" 
                           href="?tab=admins<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-user-shield me-1"></i> Admins
                        </a>
                    </li>
                </ul>

                <!-- Users Table -->
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo ucfirst($active_tab); ?> Users</h5>
                        <div>
                            <span class="badge bg-light text-dark">Total: <?php echo number_format($total_rows); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <?php if ($active_tab == 'doctors'): ?>
                                            <th>Department</th>
                                        <?php endif; ?>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($users_result && $users_result->num_rows > 0): ?>
                                        <?php while ($user = $users_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3">
                                                            <?php echo getUserInitials($user['name']); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                            <div class="mt-1">
                                                                <span class="badge <?php echo $active_tab == 'doctors' ? 'doctor-badge' : ($active_tab == 'admins' ? 'admin-badge' : 'patient-badge'); ?>">
                                                                    <?php echo $active_tab == 'doctors' ? 'Doctor' : ($active_tab == 'admins' ? 'Admin' : 'Patient'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                                <?php if ($active_tab == 'doctors'): ?>
                                                    <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo formatDate($user['created_at']); ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="admin_edit_user.php?id=<?php echo $user['id']; ?>&type=<?php echo $active_tab == 'doctors' ? 'doctor' : ($active_tab == 'admins' ? 'admin' : 'patient'); ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           data-tooltip="Edit User">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="admin_view_user.php?id=<?php echo $user['id']; ?>&type=<?php echo $active_tab == 'doctors' ? 'doctor' : ($active_tab == 'admins' ? 'admin' : 'patient'); ?>"  
                                                           class="btn btn-sm btn-outline-info" 
                                                           data-tooltip="View User">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($user['id'] != $admin_id): ?>
                                                        <a href="admin_delete_user.php?id=<?php echo $user['id']; ?>&type=<?php echo $active_tab == 'doctors' ? 'doctor' : ($active_tab == 'admins' ? 'admin' : 'patient'); ?>" 
                                                           class="btn btn-sm btn-outline-danger" 
                                                           data-tooltip="Delete User"
                                                           onclick="return confirm('Are you sure you want to delete this user?');">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo $active_tab == 'doctors' ? 7 : 6; ?>" class="text-center py-4">
                                                <div class="d-flex flex-column align-items-center">
                                                    <i class="fas fa-user-slash text-muted mb-2" style="font-size: 2rem;"></i>
                                                    <h5 class="text-muted">No <?php echo $active_tab; ?> found</h5>
                                                    <?php if (!empty($search)): ?>
                                                        <p class="text-muted">Try adjusting your search query</p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-0 text-muted">
                                Showing <?php echo min($offset + 1, $total_rows); ?> to <?php echo min($offset + $results_per_page, $total_rows); ?> of <?php echo number_format($total_rows); ?> entries
                            </p>
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" 
                                           href="?tab=<?php echo $active_tab; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page - 1; ?>" 
                                           aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" 
                                           href="?tab=<?php echo $active_tab; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" 
                                           href="?tab=<?php echo $active_tab; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page + 1; ?>" 
                                           aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </main>
        </div>
    </div>

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
    </script>
</body>
</html>
<?php
// Close database connection
$stmt->close();
$count_stmt->close();
$conn->close();
?>