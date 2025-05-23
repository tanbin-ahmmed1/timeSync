<?php
// Start session
session_start();

// Check if patient is logged in
if (!isset($_SESSION['patient_logged_in']) || $_SESSION['patient_logged_in'] !== true) {
    // Redirect to login page
    header("Location: index.php");
    exit();
}

// Get patient data from session
$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];
$patient_username = $_SESSION['patient_username'];

// DATABASE CONNECTION
$host = "localhost";
$dbname = "time_sync";
$username = "root";
$password = "";

// Connect to database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Process appointment booking
$bookingSuccess = $bookingError = "";
if (isset($_POST['book_appointment'])) {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = htmlspecialchars($_POST['notes']);
    
    // Validate input
    if (empty($doctor_id) || empty($appointment_date) || empty($appointment_time)) {
        $bookingError = "Please complete all required fields";
    } else {
        try {
            // Check if the selected time slot is available
            $checkStmt = $conn->prepare("SELECT * FROM appointments 
                                         WHERE doctor_id = :doctor_id 
                                         AND appointment_date = :appointment_date 
                                         AND appointment_time = :appointment_time 
                                         AND status != 'cancelled'");
            $checkStmt->bindParam(':doctor_id', $doctor_id);
            $checkStmt->bindParam(':appointment_date', $appointment_date);
            $checkStmt->bindParam(':appointment_time', $appointment_time);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $bookingError = "This time slot is already booked. Please choose another time.";
            } else {
                // Insert new appointment
                $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, notes) 
                                       VALUES (:patient_id, :doctor_id, :appointment_date, :appointment_time, :notes)");
                $stmt->bindParam(':patient_id', $patient_id);
                $stmt->bindParam(':doctor_id', $doctor_id);
                $stmt->bindParam(':appointment_date', $appointment_date);
                $stmt->bindParam(':appointment_time', $appointment_time);
                $stmt->bindParam(':notes', $notes);
                $stmt->execute();
                
                $bookingSuccess = "Appointment booked successfully!";
            }
        } catch(PDOException $e) {
            $bookingError = "Database error: " . $e->getMessage();
        }
    }
}

// Process appointment cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $appointment_id = $_GET['cancel'];
    
    try {
        // Verify the appointment belongs to this patient
        $checkStmt = $conn->prepare("SELECT * FROM appointments WHERE id = :id AND patient_id = :patient_id");
        $checkStmt->bindParam(':id', $appointment_id);
        $checkStmt->bindParam(':patient_id', $patient_id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Update appointment status to cancelled
            $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = :id");
            $stmt->bindParam(':id', $appointment_id);
            $stmt->execute();
            
            $bookingSuccess = "Appointment cancelled successfully!";
        } else {
            $bookingError = "Invalid appointment or permission denied.";
        }
    } catch(PDOException $e) {
        $bookingError = "Database error: " . $e->getMessage();
    }
}

// Get patient information
try {
    $stmt = $conn->prepare("SELECT * FROM patient_users WHERE id = :patient_id");
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error fetching patient data: " . $e->getMessage();
}

// Get patient's appointments
try {
    $stmt = $conn->prepare("SELECT a.*, d.name as doctor_name, d.specialty 
                           FROM appointments a 
                           JOIN doctor_users d ON a.doctor_id = d.id 
                           WHERE a.patient_id = :patient_id 
                           ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error fetching appointments: " . $e->getMessage();
}

// Get all doctors for booking form
try {
    $stmt = $conn->prepare("SELECT id, name, specialty FROM doctor_users ORDER BY specialty, name");
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error fetching doctors: " . $e->getMessage();
}

// Process profile update
$updateSuccess = $updateError = "";
if (isset($_POST['update_profile'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    // Validate input
    if (empty($name) || empty($email)) {
        $updateError = "Name and email are required";
    } else {
        try {
            // Check if email is already in use by another patient
            $checkStmt = $conn->prepare("SELECT id FROM patient_users WHERE email = :email AND id != :patient_id");
            $checkStmt->bindParam(':email', $email);
            $checkStmt->bindParam(':patient_id', $patient_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $updateError = "Email is already in use by another account";
            } else {
                // If password change is requested, verify current password
                if (!empty($current_password) && !empty($new_password)) {
                    $passwordStmt = $conn->prepare("SELECT password FROM patient_users WHERE id = :patient_id");
                    $passwordStmt->bindParam(':patient_id', $patient_id);
                    $passwordStmt->execute();
                    $user = $passwordStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!password_verify($current_password, $user['password'])) {
                        $updateError = "Current password is incorrect";
                    } else {
                        // Update with new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE patient_users SET name = :name, email = :email, phone = :phone, password = :password WHERE id = :patient_id");
                        $stmt->bindParam(':password', $hashed_password);
                    }
                } else {
                    // Update without changing password
                    $stmt = $conn->prepare("UPDATE patient_users SET name = :name, email = :email, phone = :phone WHERE id = :patient_id");
                }
                
                // Complete the update
                if (!isset($updateError)) {
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':phone', $phone);
                    $stmt->bindParam(':patient_id', $patient_id);
                    $stmt->execute();
                    
                    // Update session data
                    $_SESSION['patient_name'] = $name;
                    
                    $updateSuccess = "Profile updated successfully!";
                    
                    // Refresh patient data
                    $stmt = $conn->prepare("SELECT * FROM patient_users WHERE id = :patient_id");
                    $stmt->bindParam(':patient_id', $patient_id);
                    $stmt->execute();
                    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch(PDOException $e) {
            $updateError = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - TimeSync</title>
    <link rel="icon" href="../Images/TimeSync.png" type="image/png">
    <link rel="stylesheet" href="../styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img class="icon" src="../Images/TimeSync.png" alt="timesync logo">
                    <span class="logo-text">Time Sync</span>
                </div>
                <nav>
                    <ul class="nav-links">
                        
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <aside class="sidebar" id="sidebar">
            <div class="user-info"> 
                <h3><?php echo htmlspecialchars($patient_name); ?></h3>
                <p>Patient</p>
            </div>
            
            <ul>
                <li><a href="#dashboard" class="active" data-section="dashboard-section"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#appointments" data-section="appointments-section"><i class="fas fa-calendar-alt"></i> My Appointments</a></li>
                <li><a href="#book" data-section="book-section"><i class="fas fa-plus-circle"></i> Book Appointment</a></li>
                <li><a href="#profile" data-section="profile-section"><i class="fas fa-user"></i> My Profile</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <!-- Dashboard Overview Section -->
            <section id="dashboard-section" class="content-section">
                <h2>Dashboard</h2>
                <div class="dashboard-stats">
                    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                        <div class="stat-card" style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; text-align: center;">
                            <i class="fas fa-calendar-check" style="font-size: 2rem; color: #0d47a1; margin-bottom: 10px;"></i>
                            <h3>Upcoming Appointments</h3>
                            <?php
                            // Count upcoming appointments
                            $upcomingCount = 0;
                            $today = date('Y-m-d');
                            foreach ($appointments as $appointment) {
                                if ($appointment['status'] == 'scheduled' && $appointment['appointment_date'] >= $today) {
                                    $upcomingCount++;
                                }
                            }
                            ?>
                            <p style="font-size: 1.8rem; font-weight: bold;"><?php echo $upcomingCount; ?></p>
                        </div>
                        
                        <div class="stat-card" style="background-color: #e8f5e9; padding: 20px; border-radius: 8px; text-align: center;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; color: #1b5e20; margin-bottom: 10px;"></i>
                            <h3>Completed Visits</h3>
                            <?php
                            // Count completed appointments
                            $completedCount = 0;
                            foreach ($appointments as $appointment) {
                                if ($appointment['status'] == 'completed') {
                                    $completedCount++;
                                }
                            }
                            ?>
                            <p style="font-size: 1.8rem; font-weight: bold;"><?php echo $completedCount; ?></p>
                        </div>
                        
                        <div class="stat-card" style="background-color: #fff8e1; padding: 20px; border-radius: 8px; text-align: center;">
                            <i class="fas fa-user-md" style="font-size: 2rem; color: #ff6f00; margin-bottom: 10px;"></i>
                            <h3>Different Doctors Visited</h3>
                            <?php
                            // Count unique doctors
                            $uniqueDoctors = [];
                            foreach ($appointments as $appointment) {
                                if (!in_array($appointment['doctor_id'], $uniqueDoctors)) {
                                    $uniqueDoctors[] = $appointment['doctor_id'];
                                }
                            }
                            ?>
                            <p style="font-size: 1.8rem; font-weight: bold;"><?php echo count($uniqueDoctors); ?></p>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <h3>Next Upcoming Appointment</h3>
                    <?php
                    // Get the next upcoming appointment
                    $upcomingAppointment = null;
                    foreach ($appointments as $appointment) {
                        if ($appointment['status'] == 'scheduled' && $appointment['appointment_date'] >= $today) {
                            $upcomingAppointment = $appointment;
                            break;
                        }
                    }
                    
                    if ($upcomingAppointment): 
                    ?>
                    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                            <div>
                                <h4>Dr. <?php echo htmlspecialchars($upcomingAppointment['doctor_name']); ?></h4>
                                <p><i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($upcomingAppointment['specialty']); ?></p>
                                <p><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($upcomingAppointment['appointment_date'])); ?></p>
                                <p><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($upcomingAppointment['appointment_time'])); ?></p>
                            </div>
                            <div>
                                <a href="?cancel=<?php echo $upcomingAppointment['id']; ?>" class="btn-action btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment?');">Cancel Appointment</a>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <p>You have no upcoming appointments. <a href="#book" data-section="book-section">Book one now</a>.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- My Appointments Section -->
            <section id="appointments-section" class="content-section" style="display: none;">
                <h2>My Appointments</h2>
                
                <?php if (!empty($bookingSuccess)): ?>
                <div class="alert alert-success">
                    <?php echo $bookingSuccess; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($bookingError)): ?>
                <div class="alert alert-danger">
                    <?php echo $bookingError; ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-bottom: 20px;">
                    <button id="showUpcoming" class="btn" style="margin-right: 10px;">Upcoming</button>
                    <button id="showPast" class="btn btn-secondary">Past</button>
                </div>
                
                <!-- Upcoming Appointments -->
                <div id="upcomingAppointments">
                    <h3>Upcoming Appointments</h3>
                    <?php if (count($appointments) > 0): ?>
                    <table class="appointments-list">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Specialty</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasUpcoming = false;
                            foreach ($appointments as $appointment):
                                if ($appointment['status'] == 'scheduled' && $appointment['appointment_date'] >= date('Y-m-d')):
                                    $hasUpcoming = true;
                            ?>
                            <tr>
                                <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['specialty']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td>
                                    <span class="appointment-status status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?cancel=<?php echo $appointment['id']; ?>" class="btn-action btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment?');">Cancel</a>
                                </td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (!$hasUpcoming):
                            ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No upcoming appointments. <a href="#book" data-section="book-section">Book now</a>.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p>No appointments found. <a href="#book" data-section="book-section">Book your first appointment</a>.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Past Appointments -->
                <div id="pastAppointments" style="display: none;">
                    <h3>Past Appointments</h3>
                    <?php if (count($appointments) > 0): ?>
                    <table class="appointments-list">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Specialty</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasPast = false;
                            foreach ($appointments as $appointment):
                                if ($appointment['status'] != 'scheduled' || $appointment['appointment_date'] < date('Y-m-d')):
                                    $hasPast = true;
                            ?>
                            <tr>
                                <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['specialty']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td>
                                    <span class="appointment-status status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (!$hasPast):
                            ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No past appointments.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p>No appointments found.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Book Appointment Section -->
            <section id="book-section" class="content-section" style="display: none;">
                <h2>Book New Appointment</h2>
                
                <?php if (!empty($bookingSuccess)): ?>
                <div class="alert alert-success">
                    <?php echo $bookingSuccess; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($bookingError)): ?>
                <div class="alert alert-danger">
                    <?php echo $bookingError; ?>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="booking-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="doctor_id">Select Doctor:</label>
                            <select id="doctor_id" name="doctor_id" required>
                                <option value="">-- Select Doctor --</option>
                                <?php
                                // Group doctors by specialty
                                $doctorsBySpecialty = [];
                                foreach ($doctors as $doctor) {
                                    $doctorsBySpecialty[$doctor['specialty']][] = $doctor;
                                }
                                
                                // Output grouped doctors
                                foreach ($doctorsBySpecialty as $specialty => $specialtyDoctors) {
                                    echo "<optgroup label=\"$specialty\">";
                                    foreach ($specialtyDoctors as $doctor) {
                                        echo "<option value=\"{$doctor['id']}\">Dr. {$doctor['name']}</option>";
                                    }
                                    echo "</optgroup>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment_date">Appointment Date:</label>
                            <?php
                            // Set minimum date to tomorrow
                            $minDate = date('Y-m-d', strtotime('+1 day'));
                            ?>
                            <input type="date" id="appointment_date" name="appointment_date" min="<?php echo $minDate; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment_time">Appointment Time:</label>
                            <select id="appointment_time" name="appointment_time" required>
                                <option value="">-- Select Time --</option>
                                <?php
                                // Generate time slots from 8:00 AM to 5:00 PM
                                $start = 8 * 60; // 8:00 AM in minutes
                                $end = 17 * 60; // 5:00 PM in minutes
                                $interval = 30; // 30-minute intervals
                                
                                for ($time = $start; $time < $end; $time += $interval) {
                                    $hour = floor($time / 60);
                                    $minute = $time % 60;
                                    
                                    // Format time for display
                                    $displayTime = date('g:i A', strtotime("$hour:$minute"));
                                    
                                    // Format time for value (24-hour format)
                                    $valueTime = date('H:i:s', strtotime("$hour:$minute"));
                                    
                                    echo "<option value=\"$valueTime\">$displayTime</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="notes">Notes (optional):</label>
                            <textarea id="notes" name="notes" rows="4" placeholder="Add any specific information for the doctor"></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="book_appointment" class="btn">Book Appointment</button>
                </form>
            </section>

            <!-- Profile Section -->
            <section id="profile-section" class="content-section" style="display: none;">
                <h2>My Profile</h2>
                
                <?php if (!empty($updateSuccess)): ?>
                <div class="alert alert-success">
                    <?php echo $updateSuccess; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($updateError)): ?>
                <div class="alert alert-danger">
                    <?php echo $updateError; ?>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="profile-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full Name:</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($patient['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" value="<?php echo htmlspecialchars($patient['username']); ?>" disabled>
                            <small>Username cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number:</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($patient['phone']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="dob">Date of Birth:</label>
                            <input type="date" id="dob" value="<?php echo htmlspecialchars($patient['dob']); ?>" disabled>
                            <small>Contact support to update date of birth</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender:</label>
                            <input type="text" id="gender" value="<?php echo htmlspecialchars($patient['gender']); ?>" disabled>
                            <small>Contact support to update gender</small>
                        </div>
                    </div>
                    
                    <h3 style="margin-top: 30px;">Change Password</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password:</label>
                            <input type="password" id="current_password" name="current_password">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password:</label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                    </div>
                    <small>Leave password fields empty if you don't want to change your password</small>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" name="update_profile" class="btn">Update Profile</button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
        
        // Navigation between sections
        const navLinks = document.querySelectorAll('.sidebar a');
        const contentSections = document.querySelectorAll('.content-section');
        
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all links
                navLinks.forEach(lnk => lnk.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Hide all sections
                contentSections.forEach(section => section.style.display = 'none');
                
                // Show the target section
                const targetSection = this.getAttribute('data-section');
                document.getElementById(targetSection).style.display = 'block';
                
                // Close mobile menu if open
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                }
            });
        });
        
        // Show/hide appointment tabs
        const showUpcoming = document.getElementById('showUpcoming');
        const showPast = document.getElementById('showPast');
        const upcomingAppointments = document.getElementById('upcomingAppointments');
        const pastAppointments = document.getElementById('pastAppointments');
        
        if (showUpcoming && showPast) {
            showUpcoming.addEventListener('click', function() {
                upcomingAppointments.style.display = 'block';
                pastAppointments.style.display = 'none';
                showUpcoming.classList.add('btn');
                showUpcoming.classList.remove('btn-secondary');
                showPast.classList.add('btn-secondary');
                showPast.classList.remove('btn');
            });
            
            showPast.addEventListener('click', function() {
                upcomingAppointments.style.display = 'none';
                pastAppointments.style.display = 'block';
                showPast.classList.add('btn');
                showPast.classList.remove('btn-secondary');
                showUpcoming.classList.add('btn-secondary');
                showUpcoming.classList.remove('btn');
            });
        }
        
        // Dynamic availability checking (simplified version)
        const doctorSelect = document.getElementById('doctor_id');
        const dateInput = document.getElementById('appointment_date');
        const timeSelect = document.getElementById('appointment_time');
        
        // Function to check availability when doctor and date are selected
        function checkAvailability() {
            const doctorId = doctorSelect.value;
            const date = dateInput.value;
            
            if (!doctorId || !date) return;
            
            // Here you would typically make an AJAX call to check availability
            // For demo purposes, we'll just simulate it
            console.log(`Checking availability for doctor ${doctorId} on ${date}`);
            
            // Simulate API response - in a real app, this would come from the server
            // This would be implemented with AJAX in a real application
        }
        
        if (doctorSelect && dateInput) {
            doctorSelect.addEventListener('change', checkAvailability);
            dateInput.addEventListener('change', checkAvailability);
        }
        
        // Direct links from other parts of the dashboard
        document.querySelectorAll('a[data-section]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetSection = this.getAttribute('data-section');
                
                // Find and click the corresponding sidebar link
                const sidebarLink = document.querySelector(`.sidebar a[data-section="${targetSection}"]`);
                if (sidebarLink) {
                    sidebarLink.click();
                }
            });
        });
    </script>
</body>
</html>