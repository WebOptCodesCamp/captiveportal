<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Setting up Captive Portal Database</h2>";

require_once 'includes/config.php';

// First, connect to MySQL without specifying database
$mysqli_setup = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($mysqli_setup->connect_error) {
    die("Connection failed: " . $mysqli_setup->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($mysqli_setup->query($sql)) {
    echo "✓ Database '" . DB_NAME . "' created or already exists<br>";
} else {
    echo "✗ Error creating database: " . $mysqli_setup->error . "<br>";
}

// Now connect to the specific database
require_once 'includes/db.php';

// Create bundles table
$sql_bundles = "CREATE TABLE IF NOT EXISTS bundles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    data_limit_mb INT NOT NULL,
    price_kes DECIMAL(10,2) NOT NULL,
    duration_minutes INT NOT NULL,
    is_unlimited BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($mysqli->query($sql_bundles)) {
    echo "✓ Bundles table created or already exists<br>";
} else {
    echo "✗ Error creating bundles table: " . $mysqli->error . "<br>";
}

// Insert sample bundles if table is empty
$result = $mysqli->query("SELECT COUNT(*) as count FROM bundles");
$count = $result->fetch_assoc()['count'];

if ($count == 0) {
    echo "Adding sample bundles...<br>";
    
    $sample_bundles = [
        ['Daily Bundle', 500, 50, 1440],
        ['Weekly Bundle', 2048, 200, 10080], 
        ['Monthly Bundle', 10240, 800, 43200]
    ];
    
    $stmt = $mysqli->prepare("INSERT INTO bundles (name, data_limit_mb, price_kes, duration_minutes) VALUES (?, ?, ?, ?)");
    
    foreach ($sample_bundles as $bundle) {
        $stmt->bind_param('sidi', $bundle[0], $bundle[1], $bundle[2], $bundle[3]);
        if ($stmt->execute()) {
            echo "✓ Added bundle: " . $bundle[0] . "<br>";
        } else {
            echo "✗ Error adding bundle: " . $stmt->error . "<br>";
        }
    }
} else {
    echo "✓ Bundles already exist (" . $count . " bundles)<br>";
}

echo "<br><strong>Database setup completed!</strong><br>";
echo "<a href='index.php'>Go to Home Page</a>";
?>
