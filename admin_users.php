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
$admin_query = "SELECT name as full_name, role_level FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

// Determine active tab (default to patients)
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['patients', 'doctors', 'admins']) ? $_GET['tab'] : 'patients';

// Only allow super admins to access the admins tab
if ($active_tab == 'admins' && $admin_data['role_level'] != 'super_admin') {
    $active_tab = 'patients';
}

// Handle search functionality
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Build the query based on active tab
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;

if ($active_tab == 'patients') {
    // Patients query
    $query = "SELECT * FROM patient_users";
    $count_query = "SELECT COUNT(*) as total FROM patient_users";
    
    if (!empty($search)) {
        $query .= " WHERE name LIKE ? OR email LIKE ? OR username LIKE ?";
        $count_query .= " WHERE name LIKE ? OR email LIKE ? OR username LIKE ?";
        $search_param = "%$search%";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?, ?";
} elseif ($active_tab == 'doctors') {
    // Doctors query
    $query = "SELECT * FROM doctor_users";
    $count_query = "SELECT COUNT(*) as total FROM doctor_users";
    
    if (!empty($search)) {
        $query .= " WHERE name LIKE ? OR email LIKE ? OR username LIKE ? OR department LIKE ?";
        $count_query .= " WHERE name LIKE ? OR email LIKE ? OR username LIKE ? OR department LIKE ?";
        $search_param = "%$search%";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?, ?";
} else {
    // Admins query (only for super admins)
    $query = "SELECT * FROM admin_users";
    $count_query = "SELECT COUNT(*) as total FROM admin_users";
    
    if (!empty($search)) {
        $query .= " WHERE name LIKE ? OR email LIKE ? OR username LIKE ? OR role_level LIKE ?";
        $count_query .= " WHERE name LIKE ? OR email LIKE ? OR username LIKE ? OR role_level LIKE ?";
        $search_param = "%$search%";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?, ?";
}

// Get total number of users
$count_stmt = $conn->prepare($count_query);
if (!empty($search)) {
    if ($active_tab == 'patients') {
        $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    } elseif ($active_tab == 'doctors') {
        $count_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    } else {
        $count_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    }
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $results_per_page);

// Get users data with pagination
$stmt = $conn->prepare($query);
if (!empty($search)) {
    if ($active_tab == 'patients') {
        $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $offset, $results_per_page);
    } elseif ($active_tab == 'doctors') {
        $stmt->bind_param("ssssii", $search_param, $search_param, $search_param, $search_param, $offset, $results_per_page);
    } else {
        $stmt->bind_param("ssssii", $search_param, $search_param, $search_param, $search_param, $offset, $results_per_page);
    }
} else {
    $stmt->bind_param("ii", $offset, $results_per_page);
}
$stmt->execute();
$users_result = $stmt->get_result();
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
        
        /* Additional styles for users page */
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .search-box {
            max-width: 300px;
        }
        
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            font-weight: 600;
        }
        
        .doctor-badge {
            background-color: #e6f7ff;
            color: #1890ff;
        }
        
        .patient-badge {
            background-color: #f6ffed;
            color: #52c41a;
        }
        
        .admin-badge {
            background-color: #f9f0ff;
            color: #722ed1;
        }
        
        .role-badge {
            font-size: 0.7rem;
            padding: 3px 6px;
            border-radius: 4px;
            margin-left: 5px;
        }
        
        .role-super {
            background-color: #ffccc7;
            color: #cf1322;
        }
        
        .role-admin {
            background-color: #b5f5ec;
            color: #08979c;
        }
        
        .role-support {
            background-color: #d6e4ff;
            color: #1d39c4;
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
                        <?php if ($admin_data['role_level'] == 'super_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-dark" href="admin_system_stats.php">
                                <i class="fas fa-chart-line me-2"></i> System Statistics
                            </a>
                        </li>
                        <?php endif; ?>
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
                        <a href="?toggle_menu=user" class="btn btn-outline-secondary <?php echo $showUserMenu ? 'active' : ''; ?>">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($admin_data['full_name']); ?>
                            <?php if ($admin_data['role_level']): ?>
                                <span class="role-badge role-<?php echo str_replace('_', '', $admin_data['role_level']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $admin_data['role_level'])); ?>
                                </span>
                            <?php endif; ?>
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
                            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                            <div class="input-group search-box">
                                <input type="text" class="form-control" name="search" placeholder="Search <?php echo $active_tab; ?>..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a href="admin_add_user.php?type=<?php echo $active_tab == 'doctors' ? 'doctor' : ($active_tab == 'admins' ? 'admin' : 'patient'); ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add New <?php echo ucfirst($active_tab == 'doctors' ? 'Doctor' : ($active_tab == 'admins' ? 'Admin' : 'Patient')); ?>
                        </a>
                    </div>
                </div>

                <!-- Users Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'patients' ? 'active' : ''; ?>" href="?tab=patients<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-user-injured me-1"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'doctors' ? 'active' : ''; ?>" href="?tab=doctors<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-user-md me-1"></i> Doctors
                        </a>
                    </li>
                    <?php if ($admin_data['role_level'] == 'super_admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'admins' ? 'active' : ''; ?>" href="?tab=admins<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                            <i class="fas fa-user-shield me-1"></i> Admins
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- Users Table -->
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo ucfirst($active_tab); ?> Users</h5>
                        <div>
                            <span class="badge bg-light text-dark">Total: <?php echo $total_rows; ?></span>
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
                                        <?php elseif ($active_tab == 'admins'): ?>
                                            <th>Role</th>
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
                                                        <div class="user-avatar me-2">
                                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                            <div>
                                                                <span class="badge <?php echo $active_tab == 'doctors' ? 'doctor-badge' : ($active_tab == 'admins' ? 'admin-badge' : 'patient-badge'); ?>">
                                                                    <?php echo $active_tab == 'doctors' ? 'Doctor' : ($active_tab == 'admins' ? 'Admin' : 'Patient'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                                                <?php if ($active_tab == 'doctors'): ?>
                                                    <td><?php echo htmlspecialchars($user['department'] ?: 'N/A'); ?></td>
                                                <?php elseif ($active_tab == 'admins'): ?>
                                                    <td>
                                                        <?php if ($user['role_level']): ?>
                                                            <span class="role-badge role-<?php echo str_replace('_', '', $user['role_level']); ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $user['role_level'])); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <?php 
                                                        $reg_date = new DateTime($user['created_at']);
                                                        echo $reg_date->format('M d, Y');
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex">
                                                        <a href="admin_edit_user.php?id=<?php echo $user['id']; ?>&type=<?php echo $active_tab == 'doctors' ? 'doctor' : ($active_tab == 'admins' ? 'admin' : 'patient'); ?>" 
                                                           class="btn btn-sm btn-outline-primary me-1" 
                                                           data-tooltip="Edit User">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="admin_view_user.php?id=<?php echo $user['id']; ?>&type=<?php echo $active_tab == 'doctors' ? 'doctor' : ($active_tab == 'admins' ? 'admin' : 'patient'); ?>" 
                                                           class="btn btn-sm btn-outline-info me-1" 
                                                           data-tooltip="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($active_tab != 'admins' || ($active_tab == 'admins' && $user['id'] != $admin_id)): ?>
                                                        <form method="POST" action="admin_delete_user.php" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="user_type" value="<?php echo $active_tab == 'doctors' ? 'doctor' : ($active_tab == 'admins' ? 'admin' : 'patient'); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-tooltip="Delete User">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo $active_tab == 'doctors' ? '7' : ($active_tab == 'admins' ? '7' : '6'); ?>" class="text-center py-4">
                                                <?php if (!empty($search)): ?>
                                                    No <?php echo $active_tab; ?> found matching your search criteria.
                                                <?php else: ?>
                                                    No <?php echo $active_tab; ?> found in the system.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?tab=<?php echo $active_tab; ?>&page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?tab=<?php echo $active_tab; ?>&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?tab=<?php echo $active_tab; ?>&page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>