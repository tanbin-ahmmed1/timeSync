<!DOCTYPE html>
<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TimeSync Medical Appointment System</title>
    <link rel="icon" href="Images/TimeSync.png" type="image/png">
    <link rel="stylesheet" href="styles.css">

    <script>
        // Add FontAwesome CDN
        document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">');
    </script>
</head>
<body>
<?php
// Start session
session_start();

// DATABASE CONNECTION
$host = "localhost";
$dbname = "time_sync";
$username = "root"; 
$password = ""; 

// Initialize variables
$error = "";
$success = "";
$userType = 'patient'; // Only allow patient registration

// Connect to database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Process registration form
if (isset($_POST['register'])) {
    // Get and sanitize form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $userType = 'patient'; // Force patient type only
    
    //Additional fields
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    
    // Validate input
    if (empty($name) || empty($email) || empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Please complete all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (empty($dob) || empty($gender)) {
        $error = "Please complete all patient information";
    } else {
        try {
            $table = "patient_users";
            $redirectPage = "patient_dashboard.php";
            $sessionPrefix = "patient";
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or email already exists";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Prepare SQL for patient registration
                $sql = "INSERT INTO $table (name, email, username, password, dob, gender, phone, created_at) 
                        VALUES (:name, :email, :username, :password, :dob, :gender, :phone, NOW())";
                
                // Prepare and execute statement
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':dob', $dob);
                $stmt->bindParam(':gender', $gender);
                $stmt->bindParam(':phone', $phone);
                
                $stmt->execute();
                
                // Set success message
                $success = "Registration successful! You can now login.";
                
                // Optionally auto-login after registration
                /*
                // Get the newly created user ID
                $userId = $conn->lastInsertId();
                
                // Create session variables
                $_SESSION[$sessionPrefix . "_logged_in"] = true;
                $_SESSION[$sessionPrefix . "_id"] = $userId;
                $_SESSION[$sessionPrefix . "_username"] = $username;
                $_SESSION[$sessionPrefix . "_name"] = $name;
                $_SESSION["user_type"] = $userType;
                
                // Redirect to dashboard
                header("Location: $redirectPage");
                exit();
                */
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img class="icon" src="Images/TimeSync.png" alt="timesync logo">
                   <a href=index.php><span class="logo-text">Time Sync</span></a>
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
                        <li><a href="index.php" id="homeBtn">Home</a></li>
                        <li><a href="index.php" id="loginBtn" class="btn">Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <section class="register-section">
        <div class="container">
            <div class="register-container">
                <h2>Patient Registration</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <?php echo $success; ?>
                        <p><a href="index.php" class="btn">Login Now</a></p>
                    </div>
                <?php else: ?>
                
                <div class="register-info">
                    <p>Create your patient account to book and manage medical appointments.</p>
                    <p class="note"><i class="fas fa-info-circle"></i> Note: Doctor and administrator accounts can only be created by system administrators.</p>
                </div>
                
                <form class="register-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="userType" value="patient">
                    
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="dob">Date of Birth *</label>
                        <input type="date" id="dob" name="dob" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">-- Select Gender --</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer_not_to_say">Prefer not to say</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required>
                        <small>Minimum 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-group terms-checkbox">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the <a href="Legal/terms.html" target="_blank">Terms of Service</a> and <a href="Legal/privacy.html" target="_blank">Privacy Policy</a></label>
                    </div>
                    
                    <button type="submit" name="register" class="register-btn">Create Account</button>
                    
                    <div class="form-footer">
                        <p>Already have an account? <a href="index.php">Login</a></p>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3 id="Contact">Contact Us</h3>
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
        // Form validation script
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.register-form');
            
            if (form) {
                form.addEventListener('submit', function(event) {
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    const terms = document.getElementById('terms');
                    
                    let isValid = true;
                    
                    // Password validation
                    if (password.value.length < 8) {
                        alert('Password must be at least 8 characters long');
                        isValid = false;
                    }
                    
                    // Password match validation
                    if (password.value !== confirmPassword.value) {
                        alert('Passwords do not match');
                        isValid = false;
                    }
                    
                    // Terms checkbox validation
                    if (!terms.checked) {
                        alert('You must agree to the Terms of Service and Privacy Policy');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        event.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>