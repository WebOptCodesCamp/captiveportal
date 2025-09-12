<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...<br>";

require_once 'includes/config.php';
require_once 'includes/db.php';

echo "Database connection successful!<br>";

// Check if users table exists
$result = $mysqli->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "Users table does NOT exist!<br>";
}

// Check if bundles table exists
$result = $mysqli->query("SHOW TABLES LIKE 'bundles'");
if ($result->num_rows > 0) {
    echo "Bundles table exists!<br>";
} else {
    echo "Bundles table does NOT exist!<br>";
}

echo "Test completed.";
?>
