<?php
// ----------------------------------------------------------------
// --- Database Connection ---
// ----------------------------------------------------------------

// Include the configuration file
require_once 'config.php';

// Create a new MySQLi object for database connection
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check for connection errors
if ($mysqli->connect_error) {
    // If a connection error occurs, terminate the script and display an error message.
    // This is a critical error, and the application cannot proceed without a database connection.
    die('<strong>Database Connection Failed:</strong> ' . $mysqli->connect_error);
}

// Set the character set to utf8mb4 for full Unicode support
$mysqli->set_charset('utf8mb4');

?>
