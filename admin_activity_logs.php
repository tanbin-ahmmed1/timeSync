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

// Pagination settings
$records_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Filter settings
$filter_action = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($filter_action)) {
    $where_conditions[] = "l.action_type = ?";
    $params[] = $filter_action;
    $param_types .= 's';
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(l.timestamp) >= ?";
    $params[] = $filter_date_from;
    $param_types .= 's';
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(l.timestamp) <= ?";
    $params[] = $filter_date_to;
    $param_types .= 's';
}

if (!empty($search_query)) {
    $where_conditions[] = "(l.action_details LIKE ? OR a.name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $param_types .= 'ss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
}

// Get total count for pagination - Updated query to match your table structure
$count_query = "SELECT COUNT(*) as total_count 
                FROM admin_activity_log l
                LEFT JOIN admin_users a ON l.admin_id = a.id
                $where_clause";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_query);
}

$total_records = $count_result->fetch_assoc()['total_count'];
$total_pages = ceil($total_records / $records_per_page);

// Get activity logs with pagination and filters - Updated query to match your table structure
$activity_query = "SELECT l.log_id, l.action_type, l.timestamp, l.action_details, a.name as full_name
                  FROM admin_activity_log l
                  LEFT JOIN admin_users a ON l.admin_id = a.id
                  $where_clause
                  ORDER BY l.timestamp DESC
                  LIMIT ? OFFSET ?";

// Add pagination parameters
$params[] = $records_per_page;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($params)) {
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $activity_logs = $stmt->get_result();
} else {
    // Fallback for simple query without parameters
    $simple_query = "SELECT l.log_id, l.action_type, l.timestamp, l.action_details, a.name as full_name
                    FROM admin_activity_log l
                    LEFT JOIN admin_users a ON l.admin_id = a.id
                    ORDER BY l.timestamp DESC
                    LIMIT $records_per_page OFFSET $offset";
    $activity_logs = $conn->query($simple_query);
}

// Get unique action types for filter dropdown
$action_types_query = "SELECT DISTINCT action_type FROM admin_activity_log ORDER BY action_type";
$action_types_result = $conn->query($action_types_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - TimeSync Admin</title>
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

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .filter-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .pagination {
            justify-content: center;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.035);
        }

        .action-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                            <a class="nav-link text-dark" href="admin_settings.php">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_activity_logs.php">
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
                    <h1 class="h2">Activity Logs</h1>
                    <div class="dropdown">
                        <a href="?toggle_menu=user<?php echo !empty($_SERVER['QUERY_STRING']) ? '&' . str_replace('toggle_menu=user', '', $_SERVER['QUERY_STRING']) : ''; ?>" 
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

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" action="admin_activity_logs.php">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="action_type" class="form-label">Action Type</label>
                                <select class="form-select" id="action_type" name="action_type">
                                    <option value="">All Actions</option>
                                    <?php if ($action_types_result && $action_types_result->num_rows > 0): ?>
                                        <?php while ($action_type = $action_types_result->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($action_type['action_type']); ?>" 
                                                    <?php echo $filter_action == $action_type['action_type'] ? 'selected' : ''; ?>>
                                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($action_type['action_type']))); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($filter_date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($filter_date_to); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search in details or admin name..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="admin_activity_logs.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Results Summary -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="text-muted mb-0">
                            Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?> to 
                            <?php echo min($offset + $records_per_page, $total_records); ?> of 
                            <?php echo $total_records; ?> entries
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <?php if (!empty($filter_action) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($search_query)): ?>
                            <span class="badge bg-info">Filtered Results</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity Logs Table -->
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            System Activity Logs
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($activity_logs && $activity_logs->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Action</th>
                                            <th>Admin</th>
                                            <th>Details</th>
                                            <th>Date & Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($log = $activity_logs->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                            $icon_class = '';
                                                            $icon_bg = '';
                                                            $action_type_upper = strtoupper($log['action_type']);
                                                            
                                                            // Handle different action types including delete_user
                                                            switch($action_type_upper) {
                                                                case 'CREATE':
                                                                case 'ADD':
                                                                    $icon_class = 'fa-plus';
                                                                    $icon_bg = 'bg-success text-white';
                                                                    break;
                                                                case 'UPDATE':
                                                                case 'EDIT':
                                                                    $icon_class = 'fa-edit';
                                                                    $icon_bg = 'bg-warning text-dark';
                                                                    break;
                                                                case 'DELETE':
                                                                case 'DELETE_USER':
                                                                    $icon_class = 'fa-trash';
                                                                    $icon_bg = 'bg-danger text-white';
                                                                    break;
                                                                case 'LOGIN':
                                                                    $icon_class = 'fa-sign-in-alt';
                                                                    $icon_bg = 'bg-info text-white';
                                                                    break;
                                                                case 'LOGOUT':
                                                                    $icon_class = 'fa-sign-out-alt';
                                                                    $icon_bg = 'bg-secondary text-white';
                                                                    break;
                                                                case 'VIEW':
                                                                    $icon_class = 'fa-eye';
                                                                    $icon_bg = 'bg-primary text-white';
                                                                    break;
                                                                default:
                                                                    $icon_class = 'fa-info-circle';
                                                                    $icon_bg = 'bg-primary text-white';
                                                            }
                                                        ?>
                                                        <div class="activity-icon <?php echo $icon_bg; ?> me-2">
                                                            <i class="fas <?php echo $icon_class; ?>"></i>
                                                        </div>
                                                        <span class="action-badge badge <?php 
                                                            switch($action_type_upper) {
                                                                case 'CREATE':
                                                                case 'ADD':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'UPDATE':
                                                                case 'EDIT':
                                                                    echo 'bg-warning text-dark';
                                                                    break;
                                                                case 'DELETE':
                                                                case 'DELETE_USER':
                                                                    echo 'bg-danger';
                                                                    break;
                                                                case 'LOGIN':
                                                                    echo 'bg-info';
                                                                    break;
                                                                case 'LOGOUT':
                                                                    echo 'bg-secondary';
                                                                    break;
                                                                case 'VIEW':
                                                                    echo 'bg-primary';
                                                                    break;
                                                                default:
                                                                    echo 'bg-primary';
                                                            }
                                                        ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($log['action_type']))); ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown Admin'); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 300px;" 
                                                          title="<?php echo htmlspecialchars($log['action_details']); ?>">
                                                        <?php echo htmlspecialchars($log['action_details']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $log_time = new DateTime($log['timestamp']);
                                                        $now = new DateTime();
                                                        $diff = $now->diff($log_time);
                                                        
                                                        if ($diff->d == 0) {
                                                            echo $log_time->format('h:i A');
                                                            echo '<br><small class="text-muted">Today</small>';
                                                        } elseif ($diff->d == 1) {
                                                            echo $log_time->format('h:i A');
                                                            echo '<br><small class="text-muted">Yesterday</small>';
                                                        } else {
                                                            echo $log_time->format('M d, Y');
                                                            echo '<br><small class="text-muted">' . $log_time->format('h:i A') . '</small>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Activity Logs Found</h5>
                                <p class="text-muted">
                                    <?php if (!empty($filter_action) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($search_query)): ?>
                                        No logs match your current filter criteria. Try adjusting your filters.
                                    <?php else: ?>
                                        No activity logs are available at this time.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Activity logs pagination" class="mt-4">
                        <ul class="pagination">
                            <!-- Previous Page -->
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php 
                                        $prev_params = $_GET;
                                        $prev_params['page'] = $current_page - 1;
                                        echo '?' . http_build_query($prev_params);
                                    ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php 
                                        $page_params = $_GET;
                                        $page_params['page'] = $i;
                                        echo '?' . http_build_query($page_params);
                                    ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php 
                                        $next_params = $_GET;
                                        $next_params['page'] = $current_page + 1;
                                        echo '?' . http_build_query($next_params);
                                    ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <!-- Pagination Info -->
                    <div class="text-center text-muted">
                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>