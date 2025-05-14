<?php
/**
 * Database Connection Script for TimeSync
 * 
 * This file establishes a secure connection to the MySQL database
 * and provides error handling for connection failures.
 */

// Database configuration
$db_host = 'localhost';     
$db_name = 'timesync_db';  
$db_user = 'timesync_user'; 
$db_pass = 'your_secure_password';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    // Log the error
    error_log("Database connection failed: " . $conn->connect_error);
    
    // Display user-friendly error message
    die("We're experiencing technical difficulties. Please try again later or contact support.");
}

// Set character set to prevent issues with special characters
$conn->set_charset("utf8mb4");

// Optional: Set timezone for date/time operations
date_default_timezone_set('UTC'); // Change to your timezone if needed

// Return the connection object to be used in other scripts
return $conn;
?>