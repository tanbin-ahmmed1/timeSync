<?php
session_start();

// Check if user is already logged in
    $isLoggedIn = isset($_SESSION['user_id']);

//DATABASE CONNECTION
    $host = 'localhost';

include 'db_connection.php';

// Initialize variables
$error = "";
$username = "";
$userType = ""; // To store which type of user is logging in

    // Create necessary tables if they don't exist
try {
    // Admin users table
    $conn->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(15),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Doctor users table
    $conn->exec("CREATE TABLE IF NOT EXISTS doctor_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        department VARCHAR(100),
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(15),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
        //patient users table
    $conn->exec("CREATE TABLE IF NOT EXISTS patient_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(15),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    

    
} catch(PDOException $e) {
    die("Database setup error: " . $e->getMessage());
}
            
// Get active tab or set default
$activeTab = isset($_GET['type']) ? $_GET['type'] : 'admin';

// Display login errors if any
if (isset($_SESSION['login_error'])) {
    echo '<div class="error-message">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
    unset($_SESSION['login_error']);
}

// Display registration success message
if (isset($_SESSION['registration_success'])) {
    echo '<div class="success-message">' . htmlspecialchars($_SESSION['registration_success']) . '</div>';
    unset($_SESSION['registration_success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TimeSync Login</title>
    <link rel="icon" href="TimeSync.png" type="image/png">
    <link rel="stylesheet" href="stylesLogin.css">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h1>TimeSync Login</h1>
            <div class="tabs">
                <a href="login.php?type=admin" class="tab-btn <?php echo $activeTab == 'admin' ? 'active' : ''; ?>">Admin</a>
                <a href="login.php?type=doctor" class="tab-btn <?php echo $activeTab == 'doctor' ? 'active' : ''; ?>">Doctor</a>
                <a href="login.php?type=patient" class="tab-btn <?php echo $activeTab == 'patient' ? 'active' : ''; ?>">Patient</a>
            </div>
            
            <div class="form-container">
                <form id="login-form" action="login.php" method="post">
                    <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($activeTab); ?>">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="login-btn">Login</button>
                    </div>
                    
                    <div class="register-link">
                        <p>Don't have an account? <a href="register.php?type=<?php echo htmlspecialchars($activeTab); ?>">Register here</a></p>
                        <p><a href="forgotPassword.php?type=<?php echo htmlspecialchars($activeTab); ?>">Forgot Password?</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>