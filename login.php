<?php

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
            
            <div class="tabs">
                <a href="index.php?type=admin" class="tab-btn <?php echo $activeTab == 'admin' ? 'active' : ''; ?>">Admin</a>
                <a href="index.php?type=doctor" class="tab-btn <?php echo $activeTab == 'doctor' ? 'active' : ''; ?>">Doctor</a>
                <a href="index.php?type=patient" class="tab-btn <?php echo $activeTab == 'patient' ? 'active' : ''; ?>">Patient</a>
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