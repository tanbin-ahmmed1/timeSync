<?php
// Start session
session_start();

require 'vendor/autoload.php'; // Include Composer's autoloader

// SendGrid API configuration
$sendgridApiKey='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';

// Import the SendGrid classes at the very top
use SendGrid\Mail\Mail;
use SendGrid\Email;

// DATABASE CONNECTION
$host = "localhost";
$dbname = "time_sync";
$username = "root"; 
$password = ""; 

// Initialize variables
$error = "";
$success = "";

// Connect to database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Process password reset request
if (isset($_POST['reset_request'])) {
    // Get and sanitize form data
    $email = trim($_POST['email']);
    $userType = $_POST['userType'];
    
    // Validate input
    if (empty($email) || empty($userType)) {
        $error = "Please complete all fields";
    } else {
        // Check if email exists based on user type
        try {
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
                    $error = "Invalid user type";
                    break;
            }
            
            if (!empty($table)) {
                // Check if email exists
                $stmt = $conn->prepare("SELECT id, username, email FROM $table WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Generate token for password reset
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                    
                    // Store token in database
                    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, user_type, token, expiry) VALUES (:user_id, :user_type, :token, :expiry)");
                    $stmt->bindParam(':user_id', $user['id']);
                    $stmt->bindParam(':user_type', $userType);
                    $stmt->bindParam(':token', $token);
                    $stmt->bindParam(':expiry', $expiry);
                    $stmt->execute();
                    
                    // Create reset link
                    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token . "&user_type=" . $userType;
                    
                    // Send email with password reset link
                    $to = $email;
                    $subject = "TimeSync Password Reset";
                    
                    // Create HTML email message
                    $message = '
                    <html>
                    <head>
                        <title>TimeSync Password Reset</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background-color: #3498db; padding: 15px; text-align: center; }
                            .header img { max-height: 50px; }
                            .header h1 { color: white; margin: 0; }
                            .content { background-color: #f9f9f9; padding: 20px; border-radius: 5px; }
                            .button { display: inline-block; padding: 10px 20px; background-color: #3498db; color: white; 
                                   text-decoration: none; border-radius: 5px; margin: 20px 0; }
                            .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h1>TimeSync</h1>
                            </div>
                            <div class="content">
                                <h2>Password Reset Request</h2>
                                <p>Hello,</p>
                                <p>We received a request to reset your password for your TimeSync account. Click the button below to reset your password:</p>
                                <p style="text-align: center;">
                                    <a href="' . $resetLink . '" class="button">Reset Your Password</a>
                                </p>
                                <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                                <p>This password reset link will expire in 1 hour.</p>
                            </div>
                            <div class="footer">
                                <p>&copy; ' . date("Y") . ' TimeSync. All rights reserved.</p>
                                <p>This is an automated message, please do not reply to this email.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ';
                    

$email = new Mail();
$email->setFrom("sahilmahbub13@gmail.com", "TimeSync"); // Replace with your verified sender email and name
$email->addTo($to, ""); // Add recipient email and optional name
$email->setSubject($subject);
$email->addContent("text/plain", strip_tags($message)); // Plain text version for email clients that don't support HTML
$email->addContent("text/html", $message); // HTML version

$sendgrid = new \SendGrid($sendgridApiKey);

try {
    $response = $sendgrid->send($email);
    if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
        $success = "A password reset link has been sent to your email address. Please check your inbox.";
    } else {
        $error = "Failed to send password reset email. Please try again later.";
        // Optionally log the error for debugging:
        // error_log("SendGrid Error: " . $response->statusCode() . " - " . $response->body());
    }
} catch (Exception $e) {
    $error = 'Caught exception: ' . $e->getMessage();
    // Optionally log the exception:
    // error_log("SendGrid Exception: " . $e->getMessage());
}
                } else {
                    $error = "Email address not found.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}   
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - TimeSync</title>
    <link rel="icon" href="Images/TimeSync.png" type="image/png">
    <link rel="stylesheet" href="styles.css">
    <script>
        // Add FontAwesome CDN
        document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">');
    </script>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img class="icon" src="Images/TimeSync.png" alt="timesync logo">
                    <span class="logo-text"> <a href=index.php>Time Sync</a></span>
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
            <form class="reset-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <h2>Reset Your Password</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <?php echo $success; ?>
                        
                        <?php if (isset($_SESSION['reset_link'])): ?>
                            <div class="reset-link">
                                <?php echo $_SESSION['reset_link']; ?>
                                <?php unset($_SESSION['reset_link']); ?>
                            </div>
                            <p class="reset-note"><small>Note: This reset link is displayed here for development purposes only.</small></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>Enter your email address and select your user type below. We'll send you a link to reset your password.</p>
                    
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
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <button type="submit" name="reset_request" class="reset-btn">Send Reset Link</button>
                <?php endif; ?>
                
                <div class="back-to-login">
                    <p>Remember your password? <a href="index.php">Back to Login</a></p>
                </div>
            </form>
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
</body>
</html>