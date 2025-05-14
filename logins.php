<?php
session_start();

// Check if user is already logged in
$isLoggedIn = isset($_SESSION['user_id']);
if ($isLoggedIn) {
    // Redirect based on user type
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit;
    } else if ($_SESSION['user_type'] === 'doctor') {
        header("Location: doctor_dashboard.php");
        exit;
    } else if ($_SESSION['user_type'] === 'patient') {
        header("Location: patient_dashboard.php");
        exit;
    }
}

// Get database connection
$conn = require 'db_connection.php';

// Initialize variables
$error = "";
$username = "";
$userType = ""; // To store which type of user is logging in

// Create necessary tables if they don't exist
try {
    // Admin users table
    $conn->query("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(15),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Doctor users table
    $conn->query("CREATE TABLE IF NOT EXISTS doctor_users (
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
    $conn->query("CREATE TABLE IF NOT EXISTS patient_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(15),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create default admin account if it doesn't exist
    $checkAdmin = $conn->query("SELECT COUNT(*) as count FROM admin_users WHERE username = 'admin'");
    $adminCount = $checkAdmin->fetch_assoc();
    if ($adminCount['count'] == 0) {
        $adminPassword = password_hash("admin123", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin_users (username, password, name, email, phone) 
                               VALUES ('admin', ?, 'System Administrator', 'admin@timesync.com', '1234567890')");
        $stmt->bind_param("s", $adminPassword);
        $stmt->execute();
        $stmt->close();
    }
    
    // Create default doctor account if it doesn't exist
    $checkDoctor = $conn->query("SELECT COUNT(*) as count FROM doctor_users WHERE username = 'doctor'");
    $doctorCount = $checkDoctor->fetch_assoc();
    if ($doctorCount['count'] == 0) {
        $doctorPassword = password_hash("doctor123", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO doctor_users (username, password, name, department, email, phone) 
                               VALUES ('doctor', ?, 'Demo Doctor', 'General Medicine', 'doctor@timesync.com', '0987654321')");
        $stmt->bind_param("s", $doctorPassword);
        $stmt->execute();
        $stmt->close();
    }
    
} catch(Exception $e) {
    die("Database setup error: " . $e->getMessage());
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $userType = $_POST['user_type'];
    
    try {
        // Determine which table to query based on user type
        $table = '';
        switch ($userType) {
            case 'admin':
                $table = 'admin_users';
                $redirectPage = 'admin_dashboard.php';
                break;
            case 'doctor':
                $table = 'doctor_users';
                $redirectPage = 'doctor_dashboard.php';
                break;
            case 'patient':
                $table = 'patient_users';
                $redirectPage = 'patient_dashboard.php';
                break;
            default:
                throw new Exception("Invalid user type");
        }
        
        // Query the database for the user
        $stmt = $conn->prepare("SELECT id, username, password, name FROM {$table} WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['user_type'] = $userType;
                
                // Redirect to appropriate dashboard
                header("Location: $redirectPage");
                exit;
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Username not found";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error = "Login error: " . $e->getMessage();
    }
}
            
// Get active tab or set default
$activeTab = isset($_GET['type']) ? $_GET['type'] : 'admin';

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
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php
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
                
                <form id="login-form" action="login.php" method="post">
                    <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($activeTab); ?>">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
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