<?php

session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $userType = $_POST['user_type'];
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, user_type FROM users WHERE username = ? AND user_type = ?");
            $stmt->execute([$username, $userType]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Redirect based on user type
                switch ($user['user_type']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'doctor':
                        header("Location: doctor/dashboard.php");
                        break;
                    case 'patient':
                        header("Location: patient/dashboard.php");
                        break;
                    default:
                        header("Location: login.php");
                }
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Login error: " . $e->getMessage();
        }
    }
    
    // If there was an error, redirect back to login with error message
    $_SESSION['login_error'] = $error;
    header("Location: login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthcare Login System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h1>Healthcare Portal</h1>
            <?php
            session_start();
            
            $activeTab = isset($_GET['type']) ? $_GET['type'] : 'admin';
            
            if (isset($_SESSION['login_error'])) {
                echo '<div class="error-message">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                unset($_SESSION['login_error']);
            }
            
            if (isset($_SESSION['registration_success'])) {
                echo '<div class="success-message">' . htmlspecialchars($_SESSION['registration_success']) . '</div>';
                unset($_SESSION['registration_success']);
            }
            ?>
            
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
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>