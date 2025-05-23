<?php
session_start();

// Check if admin is logged in, if not redirect to login page
if (!isset($_SESSION['admin_users']) || empty($_SESSION['admin_users'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Check if required parameters are present
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    $_SESSION['error_message'] = "Invalid request parameters.";
    header("Location: admin_users.php");
    exit;
}

$user_id = (int)$_GET['id'];
$user_type = $_GET['type'];

// Prevent admin from deleting themselves
if ($user_type == 'admin' && $user_id == $_SESSION['admin_users']) {
    $_SESSION['error_message'] = "You cannot delete your own account.";
    header("Location: admin_users.php");
    exit;
}

// Determine the table based on user type
switch ($user_type) {
    case 'doctor':
        $table = 'doctor_users';
        break;
    case 'admin':
        $table = 'admin_users';
        break;
    case 'patient':
        $table = 'patient_users';
        break;
    default:
        $_SESSION['error_message'] = "Invalid user type specified.";
        header("Location: admin_users.php");
        exit;
}

// Get user information before deletion (for logging/notification)
$query = "SELECT name, email FROM $table WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Delete the user
$delete_query = "DELETE FROM $table WHERE id = ?";
$delete_stmt = $conn->prepare($delete_query);
$delete_stmt->bind_param("i", $user_id);
$success = $delete_stmt->execute();

if ($success) {
    $_SESSION['success_message'] = "User '{$user_data['name']}' ({$user_data['email']}) has been successfully deleted.";
    
    // Log the deletion activity (you would need to implement this in your admin_activity_log table)
    // $admin_id = $_SESSION['admin_users'];
    // $action = "Deleted $user_type user: {$user_data['name']} (ID: $user_id)";
    // $log_query = "INSERT INTO admin_activity_log (admin_id, action_type, action_details) VALUES (?, 'delete_user', ?)";
    // $log_stmt = $conn->prepare($log_query);
    // $log_stmt->bind_param("is", $admin_id, $action);
    // $log_stmt->execute();
} else {
    $_SESSION['error_message'] = "Failed to delete user. Please try again.";
}

$delete_stmt->close();
$conn->close();

// Redirect back to users page
header("Location: admin_users.php?tab=" . ($user_type == 'doctor' ? 'doctors' : ($user_type == 'admin' ? 'admins' : 'patients')));
exit;
?>