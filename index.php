<!DOCTYPE html>
<html lang="en">
    
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TimeSync - Medical Appointment Management System</title>
    <link rel="icon" href="TimeSync.png" type="image/png">
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

// Connect to database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is already logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Process login form
if (isset($_POST['login'])) {
    // Get and sanitize form data
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $userType = $_POST['userType'];
    
    // Validate input
    if (empty($username) || empty($password) || empty($userType)) {
        $error = "Please complete all fields";
    } else {
        // Authenticate based on user type
        try {
            $table = "";
            $redirectPage = "";
            
            switch ($userType) {
                case "admin":
                    $table = "admin_users";
                    $redirectPage = "admin_dashboard.php";
                    $sessionPrefix = "admin";
                    break;
                case "staff":
                    $table = "doctor_users";
                    $redirectPage = "doctor_dashboard.php";
                    $sessionPrefix = "doctor";
                    break;
                case "patient":
                    $table = "patient_users";
                    $redirectPage = "patient_dashboard.php";
                    $sessionPrefix = "patient";
                    break;
                default:
                    $error = "Invalid user type";
                    break;
            }
            
            if (!empty($table)) {
                // Check user credentials
                $stmt = $conn->prepare("SELECT id, username, password, name, email FROM $table WHERE username = :username OR email = :email");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $username); // Use the same variable for email
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (password_verify($password, $user["password"])) {
                            // Authentication successful - create session
                            $_SESSION[$sessionPrefix . "_logged_in"] = true;
                            $_SESSION[$sessionPrefix . "_id"] = $user["id"];
                            $_SESSION[$sessionPrefix . "_username"] = $user["username"];
                            $_SESSION[$sessionPrefix . "_name"] = $user["name"];
                            $_SESSION["user_type"] = $userType; // Store user type in session
                        
                        // Redirect to appropriate dashboard
                        header("Location: $redirectPage");
                        exit();
                    } else {
                        $error = "Invalid username or password";
                    }
                } else {
                    $error = "Invalid username or password";
                }
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    // Destroy session
    session_destroy();
    
    // Redirect to home page
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}
?>

    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-calendar-check"></i>
                    TimeSync
                </div>
                <nav>
                    <ul class="nav-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#contact">Contact</a></li>
                        <?php if ($isLoggedIn): ?>
                            <li><a href="dashboard.php">Dashboard</a></li>
                            <li><a href="?logout=true">Logout</a></li>
                        <?php else: ?>
                            <li><a href="#" id="loginBtn" class="btn">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Streamline Your Medical Appointments</h1>
                <p>TimeSync is a comprehensive medical appointment management system designed to simplify healthcare scheduling for patients, doctors, and administrators.</p>
                <div class="hero-buttons">
                    <?php if ($isLoggedIn): ?>
                        <a href="dashboard.php" class="btn">Go to Dashboard</a>
                    <?php else: ?>
                        <a href="#" id="getStartedBtn" class="btn">Get Started</a>
                        <a href="#" id="learnMoreBtn" class="btn btn-secondary">Learn More</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Our Features</h2>
                <p>TimeSync offers a comprehensive suite of tools to make healthcare scheduling efficient and user-friendly for everyone involved.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-search"></i>
                    <h3>Find Specialists</h3>
                    <p>Browse available doctors by specialty and find the right healthcare professional for your needs.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Easy Scheduling</h3>
                    <p>View open time slots and book appointments with just a few clicks, saving you time and hassle.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-bell"></i>
                    <h3>Notifications</h3>
                    <p>Receive timely reminders and confirmations for all your scheduled appointments.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-history"></i>
                    <h3>Appointment History</h3>
                    <p>Access your complete appointment history and medical records for reference.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-user-md"></i>
                    <h3>Doctor Portal</h3>
                    <p>Doctors can manage their availability and review patient schedules efficiently.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure & Compliant</h3>
                    <p>Your data is protected with HIPAA and GDPR compliant security protocols.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>How It Works</h2>
                <p>TimeSync makes healthcare scheduling a breeze with these simple steps.</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create an Account</h3>
                    <p>Sign up with your basic information to get started with TimeSync.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Find a Doctor</h3>
                    <p>Browse through our network of qualified healthcare professionals by specialty.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Book an Appointment</h3>
                    <p>Select an available time slot that fits your schedule and confirm your booking.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Receive Confirmation</h3>
                    <p>Get instant confirmation and reminders as your appointment approaches.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <h2>Ready to Simplify Your Healthcare Experience?</h2>
            <p>Join thousands of patients and doctors who are already using TimeSync to streamline their healthcare scheduling.</p>
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
            <?php else: ?>
                <a href="#" id="signupBtn" class="btn">Sign Up Now</a>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>TimeSync</h3>
                    <ul>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#team">Our Team</a></li>
                        <li><a href="#careers">Careers</a></li>
                        <li><a href="#news">News & Press</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Features</h3>
                    <ul>
                        <li><a href="#scheduling">Appointment Scheduling</a></li>
                        <li><a href="#reminders">Reminders</a></li>
                        <li><a href="#doctor-portal">Doctor Portal</a></li>
                        <li><a href="#admin-dashboard">Admin Dashboard</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="#help">Help Center</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#contact">Contact Us</a></li>
                        <li><a href="#feedback">Feedback</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Legal</h3>
                    <ul>
                        <li><a href="#terms">Terms of Service</a></li>
                        <li><a href="#privacy">Privacy Policy</a></li>
                        <li><a href="#compliance">Compliance</a></li>
                        <li><a href="#security">Security</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> TimeSync. All rights reserved.</p>
            </div>
        </div>
    </footer>

   <!-- Login Modal -->
<div id="loginModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <form class="login-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <h2>Login to TimeSync</h2>
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="userType">I am a:</label>
                <select id="userType" name="userType" required>
                    <option value="">-- Select User Type --</option>
                    <option value="admin">Administrator</option>
                    <option value="staff">Doctor/Staff</option>
                    <option value="patient">Patient</option>
                </select>
            </div>
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="login-btn">Login</button>
            <div class="form-footer">
                <p>Don't have an account? <a href="#" id="registerLink">Register</a></p>
                <p><a href="#" id="forgotPasswordLink">Forgot Password?</a></p>
            </div>
        </form>
    </div>
</div>
<script>
        // Modal functionality
        const modal = document.getElementById("loginModal");
        const loginBtn = document.getElementById("loginBtn");
        const getStartedBtn = document.getElementById("getStartedBtn");
        const signupBtn = document.getElementById("signupBtn");
        const closeBtn = document.getElementsByClassName("close")[0];

        // Functions to show/hide modal
        function showModal() {
            modal.style.display = "block";
        }

        function hideModal() {
            modal.style.display = "none";
        }

        // Add event listeners
        if (loginBtn) {
            loginBtn.addEventListener("click", showModal);
        }
        
        if (getStartedBtn) {
            getStartedBtn.addEventListener("click", showModal);
        }
        
        if (signupBtn) {
            signupBtn.addEventListener("click", showModal);
        }
        
        closeBtn.addEventListener("click", hideModal);

        // Close modal when clicking outside
        window.addEventListener("click", function(event) {
            if (event.target == modal) {
                hideModal();
            }
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                // Skip if the link is for modal
                if (this.id === "loginBtn" || this.id === "getStartedBtn" || this.id === "signupBtn" || 
                    this.id === "registerLink" || this.id === "forgotPasswordLink") {
                    return;
                }
                
                e.preventDefault();
                const targetId = this.getAttribute('href');
                
                if (targetId === "#") return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>