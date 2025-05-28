<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Database configuration
$servername = "localhost"; // Default for XAMPP
$username = "root";        // Default username for XAMPP
$password = "";            // Default password for XAMPP (usually empty)
$dbname = "comics_db";      // Replace 'comicsdb' with the actual name of your database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, 3306);


// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set the character set to avoid encoding issues
$conn->set_charset("utf8");

// Connection is successful
?>
