<?php
// Database configuration
$servername = "localhost"; // Default for XAMPP
$username = "root";        // Default username for XAMPP
$password = "";            // Default password for XAMPP (usually empty)
$dbname = "comics_db";      // Replace 'comicsdb' with the actual name of your database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set the character set to avoid encoding issues
$conn->set_charset("utf8");

// Connection is successful
?>
