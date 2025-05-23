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

// Get system statistics data

// Total appointments by status
$status_stats_query = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
$status_stats = $conn->query($status_stats_query);

// Appointments by month (last 6 months)
$monthly_appointments_query = "SELECT 
    DATE_FORMAT(appointment_datetime, '%Y-%m') as month, 
    COUNT(*) as count 
    FROM appointments 
    WHERE appointment_datetime >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month 
    ORDER BY month";
$monthly_appointments = $conn->query($monthly_appointments_query);

// User growth statistics
$user_growth_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN user_type = 'doctor' THEN 1 ELSE 0 END) as doctor_count,
    SUM(CASE WHEN user_type = 'patient' THEN 1 ELSE 0 END) as patient_count
    FROM (
        SELECT created_at, 'admin' as user_type FROM admin_users
        UNION ALL SELECT created_at, 'doctor' as user_type FROM doctor_users
        UNION ALL SELECT created_at, 'patient' as user_type FROM patient_users
    ) as all_users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month";
$user_growth = $conn->query($user_growth_query);

// Doctor specialties distribution
$specialties_query = "SELECT specialty, COUNT(*) as count FROM doctors GROUP BY specialty";
$specialties = $conn->query($specialties_query);

// System settings count
$settings_query = "SELECT COUNT(*) as count FROM system_settings";
$settings_count = $conn->query($settings_query)->fetch_assoc()['count'];

// Recent system activities
$activity_query = "SELECT l.action_type, l.timestamp, l.action_details, a.full_name
                  FROM admin_activity_log l
                  JOIN administrators a ON l.admin_id = a.admin_id
                  WHERE l.action_type IN ('SYSTEM_UPDATE', 'SETTING_CHANGE')
                  ORDER BY l.timestamp DESC
                  LIMIT 5";
$system_activities = $conn->query($activity_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Statistics - TimeSync</title>
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
        
        /* Chart container styles */
        .chart-container {
            height: 300px;
            width: 100%;
        }
        
        .stat-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
    <!-- Include Chart.js for statistics charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a class="nav-link active" href="admin_system_stats.php">
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
                    <h1 class="h2">System Statistics</h1>
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

                <!-- Quick Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Total Users</h6>
                                        <?php
                                            $total_users = $conn->query("SELECT COUNT(*) as count FROM (
                                                SELECT id FROM admin_users
                                                UNION ALL SELECT id FROM doctor_users
                                                UNION ALL SELECT id FROM patient_users
                                            ) as all_users")->fetch_assoc()['count'];
                                        ?>
                                        <h2 class="mb-0"><?php echo $total_users; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-primary">
                                        <i class="fas fa-users fa-2x text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Total Appointments</h6>
                                        <?php
                                            $total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
                                        ?>
                                        <h2 class="mb-0"><?php echo $total_appointments; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-success">
                                        <i class="fas fa-calendar-check fa-2x text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">System Settings</h6>
                                        <h2 class="mb-0"><?php echo $settings_count; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-warning">
                                        <i class="fas fa-cogs fa-2x text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3 mb-4">
                        <div class="stat-card card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase fw-bold mb-1">Active Sessions</h6>
                                        <?php
                                            // This is a placeholder - you would need to implement session tracking
                                            $active_sessions = rand(5, 20); // Example random value
                                        ?>
                                        <h2 class="mb-0"><?php echo $active_sessions; ?></h2>
                                    </div>
                                    <div class="icon-bg bg-info">
                                        <i class="fas fa-user-clock fa-2x text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <!-- Appointments by Status -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Appointments by Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Appointments -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Appointments Trend (Last 6 Months)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyAppointmentsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- User Growth -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">User Growth (Last 6 Months)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="userGrowthChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Doctor Specialties -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Doctor Specialties Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="specialtiesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent System Activities -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent System Activities</h5>
                                <a href="admin_activity_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php if ($system_activities && $system_activities->num_rows > 0): ?>
                                        <?php while ($activity = $system_activities->fetch_assoc()): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <?php
                                                            $icon_class = '';
                                                            switch($activity['action_type']) {
                                                                case 'SYSTEM_UPDATE':
                                                                    $icon_class = 'fa-sync-alt text-primary';
                                                                    break;
                                                                case 'SETTING_CHANGE':
                                                                    $icon_class = 'fa-cog text-warning';
                                                                    break;
                                                                default:
                                                                    $icon_class = 'fa-info-circle text-info';
                                                            }
                                                        ?>
                                                        <i class="fas <?php echo $icon_class; ?> me-2"></i>
                                                        <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                                        <?php echo htmlspecialchars($activity['action_details']); ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php
                                                            $activity_time = new DateTime($activity['timestamp']);
                                                            echo $activity_time->format('M d, h:i A');
                                                        ?>
                                                    </small>
                                                </div>
                                            </li>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-center">No recent system activities found</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Appointments by Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                        if ($status_stats && $status_stats->num_rows > 0) {
                            $labels = [];
                            while ($row = $status_stats->fetch_assoc()) {
                                $labels[] = "'" . htmlspecialchars($row['status']) . "'";
                            }
                            echo implode(', ', $labels);
                        }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                            if ($status_stats) {
                                $status_stats->data_seek(0); // Reset pointer
                                $data = [];
                                while ($row = $status_stats->fetch_assoc()) {
                                    $data[] = $row['count'];
                                }
                                echo implode(', ', $data);
                            }
                        ?>
                    ],
                    backgroundColor: [
                        '#4e73df', // Scheduled - blue
                        '#1cc88a', // Confirmed - green
                        '#e74a3b', // Cancelled - red
                        '#36b9cc', // Completed - teal
                        '#f6c23e', // No-show - yellow
                        '#858796'  // Other - gray
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9',
                        '#17a673',
                        '#be2617',
                        '#2c9faf',
                        '#dda20a',
                        '#707070'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: true,
                        caretPadding: 10,
                    },
                },
                cutout: '70%',
            },
        });

        // Monthly Appointments Chart
        const monthlyAppointmentsCtx = document.getElementById('monthlyAppointmentsChart').getContext('2d');
        const monthlyAppointmentsChart = new Chart(monthlyAppointmentsCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                        if ($monthly_appointments && $monthly_appointments->num_rows > 0) {
                            $labels = [];
                            while ($row = $monthly_appointments->fetch_assoc()) {
                                $labels[] = "'" . htmlspecialchars($row['month']) . "'";
                            }
                            echo implode(', ', $labels);
                        }
                    ?>
                ],
                datasets: [{
                    label: "Appointments",
                    lineTension: 0.3,
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "rgba(78, 115, 223, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: [
                        <?php 
                            if ($monthly_appointments) {
                                $monthly_appointments->data_seek(0); // Reset pointer
                                $data = [];
                                while ($row = $monthly_appointments->fetch_assoc()) {
                                    $data[] = $row['count'];
                                }
                                echo implode(', ', $data);
                            }
                        ?>
                    ],
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 7
                        }
                    },
                    y: {
                        ticks: {
                            beginAtZero: true,
                            maxTicksLimit: 5,
                            padding: 10,
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    },
                }
            }
        });

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthChart = new Chart(userGrowthCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                        if ($user_growth && $user_growth->num_rows > 0) {
                            $labels = [];
                            while ($row = $user_growth->fetch_assoc()) {
                                $labels[] = "'" . htmlspecialchars($row['month']) . "'";
                            }
                            echo implode(', ', $labels);
                        }
                    ?>
                ],
                datasets: [
                    {
                        label: "Admins",
                        backgroundColor: "#4e73df",
                        hoverBackgroundColor: "#2e59d9",
                        borderColor: "#4e73df",
                        data: [
                            <?php 
                                if ($user_growth) {
                                    $user_growth->data_seek(0); // Reset pointer
                                    $data = [];
                                    while ($row = $user_growth->fetch_assoc()) {
                                        $data[] = $row['admin_count'];
                                    }
                                    echo implode(', ', $data);
                                }
                            ?>
                        ],
                    },
                    {
                        label: "Doctors",
                        backgroundColor: "#1cc88a",
                        hoverBackgroundColor: "#17a673",
                        borderColor: "#1cc88a",
                        data: [
                            <?php 
                                if ($user_growth) {
                                    $user_growth->data_seek(0); // Reset pointer
                                    $data = [];
                                    while ($row = $user_growth->fetch_assoc()) {
                                        $data[] = $row['doctor_count'];
                                    }
                                    echo implode(', ', $data);
                                }
                            ?>
                        ],
                    },
                    {
                        label: "Patients",
                        backgroundColor: "#36b9cc",
                        hoverBackgroundColor: "#2c9faf",
                        borderColor: "#36b9cc",
                        data: [
                            <?php 
                                if ($user_growth) {
                                    $user_growth->data_seek(0); // Reset pointer
                                    $data = [];
                                    while ($row = $user_growth->fetch_assoc()) {
                                        $data[] = $row['patient_count'];
                                    }
                                    echo implode(', ', $data);
                                }
                            ?>
                        ],
                    }
                ],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    },
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 6
                        },
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            beginAtZero: true,
                            maxTicksLimit: 5,
                            padding: 10,
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    },
                }
            }
        });

        // Doctor Specialties Chart
        const specialtiesCtx = document.getElementById('specialtiesChart').getContext('2d');
        const specialtiesChart = new Chart(specialtiesCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php 
                        if ($specialties && $specialties->num_rows > 0) {
                            $labels = [];
                            while ($row = $specialties->fetch_assoc()) {
                                $labels[] = "'" . htmlspecialchars($row['specialty']) . "'";
                            }
                            echo implode(', ', $labels);
                        }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                            if ($specialties) {
                                $specialties->data_seek(0); // Reset pointer
                                $data = [];
                                while ($row = $specialties->fetch_assoc()) {
                                    $data[] = $row['count'];
                                }
                                echo implode(', ', $data);
                            }
                        ?>
                    ],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#858796', '#5a5c69', '#3a3b45', '#2e59d9', '#17a673',
                        '#2c9faf', '#dda20a', '#be2617', '#707070'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617',
                        '#5a5c69', '#3a3b45', '#1a1b22', '#1a3cb5', '#0d7d5a',
                        '#1a7a8c', '#b38a08', '#9e1d0e', '#5a5a5a'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: true,
                        caretPadding: 10,
                    },
                },
            },
        });
    </script>
</body>
</html>