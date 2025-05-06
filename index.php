<!DOCTYPE html>
<html lang="en">
    
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TimeSync - Medical Appointment Management System</title>
    <link rel="icon" href="TimeSync.png" type="image/x-icon">
    <style>
        :root {
            --primary: #2c6cff;
            --secondary: #53d8fb;
            --accent: #47b8e0;
            --light: #f5f7fa;
            --dark: #343a40;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .logo i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 25px;
        }
        
        nav ul li a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #194cb1;
        }
        
        .hero {
            height: 100vh;
            background: linear-gradient(rgba(44, 108, 255, 0.1), rgba(44, 108, 255, 0.05)), url('/api/placeholder/1400/800') center/cover;
            display: flex;
            align-items: center;
            text-align: center;
            margin-top: 70px;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: 48px;
            color: var(--dark);
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 18px;
            color: var(--dark);
            margin-bottom: 30px;
        }
        
        .hero-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .btn-secondary {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-secondary:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .features {
            padding: 80px 0;
            background-color: white;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-header h2 {
            font-size: 36px;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .section-header p {
            font-size: 18px;
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-card i {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .feature-card h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .feature-card p {
            color: #6c757d;
        }
        
        .how-it-works {
            padding: 80px 0;
            background-color: #f8f9fa;
        }
        
        .steps {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            flex-wrap: wrap;
        }
        
        .step {
            flex: 1;
            min-width: 250px;
            text-align: center;
            padding: 0 20px;
            margin-bottom: 30px;
        }
        
        .step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            font-size: 24px;
            font-weight: 700;
            margin: 0 auto 20px auto;
        }
        
        .step h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .step p {
            color: #6c757d;
        }
        
        .cta {
            padding: 80px 0;
            background-color: var(--primary);
            color: white;
            text-align: center;
        }
        
        .cta h2 {
            font-size: 36px;
            margin-bottom: 20px;
        }
        
        .cta p {
            font-size: 18px;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta .btn {
            background-color: white;
            color: var(--primary);
            font-size: 18px;
            padding: 12px 30px;
        }
        
        .cta .btn:hover {
            background-color: #e6e6e6;
        }
        
        footer {
            background-color: var(--dark);
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .footer-column h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: white;
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column ul li {
            margin-bottom: 10px;
        }
        
        .footer-column ul li a {
            color: #adb5bd;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-column ul li a:hover {
            color: white;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #495057;
        }
        
        .footer-bottom p {
            color: #adb5bd;
        }
        
        /* Login Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .login-form h2 {
            text-align: center;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 108, 255, 0.2);
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .login-btn:hover {
            background-color: #194cb1;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .form-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 36px;
            }
            
            .hero p {
                font-size: 16px;
            }
            
            .section-header h2 {
                font-size: 30px;
            }
            
            .nav-links {
                display: none;
            }
            
            .mobile-menu {
                display: block;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                margin-bottom: 10px;
            }
        }
    </style>
    <script>
        // Add FontAwesome CDN
        document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">');
    </script>
</head>
<body>
    <?php
    // Start session
    session_start();
    
    // Check if user is already logged in
    $isLoggedIn = isset($_SESSION['user_id']);
    
    // Process login form if submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email']) && isset($_POST['password'])) {
        // In a real application, you would validate credentials against a database
        // This is just a simple example
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        // Mock authentication (replace with actual database authentication)
        if ($email === "demo@timesync.com" && $password === "password") {
            // Set session variables
            $_SESSION['user_id'] = 1;
            $_SESSION['user_name'] = "Demo User";
            $_SESSION['user_type'] = "patient";
            
            // Redirect to dashboard or reload page
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $loginError = "Invalid email or password. Please try again.";
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
                <?php if (isset($loginError)): ?>
                    <div style="color: var(--danger); margin-bottom: 15px; text-align: center;">
                        <?php echo $loginError; ?>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-btn">Login</button>
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