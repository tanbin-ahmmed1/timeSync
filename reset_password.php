<?php
// Start session
session_start();

require 'vendor/autoload.php'; // Include Composer's autoloader

// DATABASE CONNECTION
$host = "localhost";
$dbname = "time_sync";
$username = "root"; 
$password = ""; 

// Initialize variables
$error = "";
$success = "";
$token = "";
$userType = "";
$validToken = false;

// Connect to database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get and validate token from URL
if (isset($_GET['token']) && !empty($_GET['token']) && isset($_GET['user_type']) && !empty($_GET['user_type'])) {
    $token = $_GET['token'];
    $userType = $_GET['user_type'];
    
    // Verify token is valid and not expired
    try {
        $stmt = $conn->prepare("SELECT * FROM password_reset_tokens WHERE token = :token AND user_type = :user_type AND expiry > NOW()");
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':user_type', $userType);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $validToken = true;
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Invalid or expired token. Please request a new password reset link.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "Missing token information. Please use the link provided in the email.";
}

// Process password reset form submission
if (isset($_POST['reset_password']) && $validToken) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate password
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Please complete all fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Determine which table to update based on user type
        $table = "";
        switch ($userType) {
            case "admin":
                $table = "admin_users";
                break;
            case "staff":
                $table = "doctor_users";
                break;
            case "patient":
                $table = "patient_users";
                break;
            default:
                $error = "Invalid user type.";
                break;
        }
        
        if (!empty($table)) {
            try {
                // Hash the new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update user's password in the database
                $stmt = $conn->prepare("UPDATE $table SET password = :password WHERE id = :user_id");
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':user_id', $tokenData['user_id']);
                $stmt->execute();
                
                // Delete the used token
                $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
                $stmt->bindParam(':token', $token);
                $stmt->execute();
                
                // Set success message
                $success = "Your password has been reset successfully. You can now login with your new password.";
                $validToken = false; // Hide the form after successful reset
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - TimeSync</title>
    <link rel="icon" href="Images/TimeSync.png" type="image/png">
    <link rel="stylesheet" href="styles.css">
    <script>
        // Add FontAwesome CDN
        document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">');
    </script>
    <style>
        .password-field {
            position: relative;
            margin-bottom: 20px;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #777;
        }

        .toggle-password2 {
            position: absolute;
            right: 10px;
            top: 70%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #777;
        }

        
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
        
        /* Password strength indicator */
        .password-strength {
            margin-top: 5px;
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .strength-weak {
            background-color: #ff4d4d;
            width: 33%;
        }
        
        .strength-medium {
            background-color: #ffd633;
            width: 66%;
        }
        
        .strength-strong {
            background-color: #00cc44;
            width: 100%;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img class="icon" src="Images/TimeSync.png" alt="timesync logo">
                    <span class="logo-text">Time Sync</span>
                </div>
                <nav>
                    <ul class="nav-links">
                        <li><a href="index.php#features">Features</a></li>
                        <li><a href="index.php#how-it-works">How It Works</a></li>
                        <li class="dropdown">
                            <a href="#">Legal <i class="fas fa-caret-down"></i></a>
                            <ul class="dropdown-content">
                                <li><a href="Legal/terms.html">Terms of Service</a></li>
                                <li><a href="Legal/privacy.html">Privacy Policy</a></li>
                                <li><a href="Legal/compliance.html">Compliance</a></li>
                                <li><a href="Legal/security.html">Security</a></li>
                            </ul>
                        </li>
                        <li><a href="index.php#Contact">Contact</a></li>
                        <li><a href="index.php">Home</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="reset-form-container">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                    <?php if (strpos($error, "Invalid or expired") !== false || strpos($error, "Missing token") !== false): ?>
                        <p>Need a new password reset link? <a href="forgot_password.php">Click here</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <?php echo $success; ?>
                    <p><a href="index.php" class="login-link">Go to Login</a></p>
                </div>
            <?php endif; ?>
            
            <?php if ($validToken): ?>
                <form class="reset-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?token=" . $token . "&user_type=" . $userType); ?>">
                    <h2>Create New Password</h2>
                    <p>Please enter your new password below.</p>
                    
                    <div class="form-group password-field">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <i class="toggle-password far fa-eye" onclick="togglePassword('new_password', this)"></i>
                        <div class="password-requirements">
                            Password must be at least 8 characters long.
                        </div>
                        <div class="password-strength" id="password-strength"></div>
                    </div>
                    
                    <div class="form-group password-field">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        <i class="toggle-password2 far fa-eye" onclick="togglePassword('confirm_password', this)"></i>
                    </div>
                    
                    <button type="submit" name="reset_password" class="reset-btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <ul>
                        <li>Email us: <a href="mailto:info@timesync.dk">info@timesync.dk</a></li>
                        <li>Phone: +01 23 45 67 89</li>
                        <li>Location: Niels Brock</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> TimeSync. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        // Password strength meter
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');
            const length = password.length;
            
            // Remove all classes
            strengthIndicator.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
            
            if (length === 0) {
                strengthIndicator.style.width = '0';
                return;
            }
            
            // Simple strength calculation (can be improved with regex for complexity)
            if (length < 8) {
                strengthIndicator.classList.add('strength-weak');
            } else if (length < 12 || !/[A-Z]/.test(password) || !/[0-9]/.test(password)) {
                strengthIndicator.classList.add('strength-medium');
            } else {
                strengthIndicator.classList.add('strength-strong');
            }
        });

        // Check if passwords match
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity("Passwords don't match");
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>