<?php
// ----------------------------------------------------------------
// --- MikroTik Connection Test Script ---
// ----------------------------------------------------------------

// Purpose: To diagnose the connection between the web server and the MikroTik router.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/mikrotik_controller.php';

// --- UI and Styling ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MikroTik Connection Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 20px auto; padding: 20px; background-color: #f9f9f9; }
        .container { background-color: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        h1 { color: #1a237e; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; margin-top: 0; }
        .status { padding: 15px; border-radius: 5px; font-weight: bold; text-align: center; margin-top: 20px; border: 1px solid; }
        .success { background-color: #e8f5e9; color: #2e7d32; border-color: #a5d6a7; }
        .error { background-color: #ffebee; color: #c62828; border-color: #ef9a9a; }
        pre { background-color: #263238; color: #eceff1; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace; }
        .details { margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px; }
        .details h2 { margin-top: 0; color: #3f51b5; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        th { background-color: #f5f5f5; color: #555; }
        tr:hover { background-color: #f1f8e9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>MikroTik Router Connection Test</h1>
        <p>This script checks if the web server can successfully connect and communicate with the MikroTik router using the credentials from <code>includes/config.php</code>.</p>
<?php

// --- Test Logic ---
$mikrotik = new MikroTikController();
$result = $mikrotik->testConnection();
print_r($result);
if ($result && $result['success']) {
    echo '<div class="status success">✅ Connection Successful</div>';
    echo '<div class="details">';
    echo '<h2>Router Details</h2>';
    echo '<table>';
    echo '<tr><th>RouterOS Version</th><td>' . htmlspecialchars($result['version']) . '</td></tr>';
    echo '<tr><th>Platform</th><td>' . htmlspecialchars($result['platform']) . '</td></tr>';
    echo '<tr><th>Uptime</th><td>' . htmlspecialchars($result['uptime']) . '</td></tr>';
    if (isset($result['identity'][0]['name'])) {
        echo '<tr><th>Identity</th><td>' . htmlspecialchars($result['identity'][0]['name']) . '</td></tr>';
    }
    echo '</table>';
    echo '</div>';
    echo '<div class="details">';
    echo '<h2>Raw Response</h2>';
    echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>';
    echo '</div>';
} else {
    echo '<div class="status error">❌ Connection Failed</div>';
    echo '<div class="details">';
    echo '<h2>Error Details</h2>';
    echo '<p>The script could not connect to the MikroTik router. Here are some things to check:</p>';
    echo '<ul>';
    echo '<li>Is the <strong>MIKROTIK_HOST</strong> (`' . MIKROTIK_HOST . '`) in <code>config.php</code> correct and reachable from the web server?</li>';
    echo '<li>Are the <strong>MIKROTIK_USERNAME</strong> and <strong>MIKROTIK_PASSWORD</strong> in <code>config.php</code> correct?</li>';
    echo '<li>Is the RouterOS API service enabled on the MikroTik router? (Check under `IP` -> `Services`)</li>';
    echo '<li>Is there a firewall rule on the MikroTik or on Windows blocking the connection? (Port: `' . MIKROTIK_PORT . '`)</li>';
    echo '</ul>';
    echo '<h2>Raw Response</h2>';
    echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>
';
    echo '</div>';
}

?>
    </div>
</body>
</html>
