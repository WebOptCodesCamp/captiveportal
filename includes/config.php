<?php
// ----------------------------------------------------------------
// --- Main Configuration File ---
// ----------------------------------------------------------------

// --- Database Configuration ---
// Database host (usually 'localhost')
define('DB_HOST', '127.0.0.1');

// Database username
define('DB_USER', 'root');

// Database password (leave empty for default XAMPP installation)
define('DB_PASS', '');

// Database name
define('DB_NAME', 'captive_portal');


// --- M-Pesa Daraja API Configuration ---
// --- Replace with your actual credentials from Safaricom Developer Portal ---

// Your app's Consumer Key
define('MPESA_CONSUMER_KEY', 'lBqHiQrD8ZJG5TaKsAJfwOVYKbjPAY8ewQ6hbwnhwDsYfd5t');

// Your app's Consumer Secret
define('MPESA_CONSUMER_SECRET', 'zojStgCPe6nxkmGOxhgh0DY05f9pQta5rJhaxlhMNyQsTAMGExS05ehsl8VGlhuq');

// Passkey for STK Push (Safaricom Sandbox Default)
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');

// Business Shortcode (Your Production Paybill or Till Number)
define('MPESA_SHORTCODE', '522533');

// Account number to be used for transactions
define('MPESA_ACCOUNT_NUMBER', '1322978298');

// M-Pesa environment ('sandbox' or 'live')
define('MPESA_ENV', 'live'); // Use 'live' for production

// The URL that Safaricom will send transaction results to.
// This MUST be a publicly accessible URL (use ngrok for local testing).
// Example: 'https://your-domain.com/callback.php'
define('MPESA_CALLBACK_URL', 'https://your-ngrok-url.ngrok.io/captiveportal/callback.php');


// --- MikroTik Configuration ---
// MikroTik RouterOS connection details
define('MIKROTIK_HOST', '192.168.10.0');           // Your MikroTik router IP
define('MIKROTIK_USERNAME', 'admin');              // MikroTik admin username  
define('MIKROTIK_PASSWORD', '42413646');      // MikroTik admin password
define('MIKROTIK_PORT', 8728);                     // RouterOS API port (default 8728)

// MikroTik integration settings
define('MIKROTIK_ENABLED', true);                  // Enable/disable MikroTik integration
define('MIKROTIK_BANDWIDTH_LIMITING', true);       // Enable bandwidth limiting per user
define('MIKROTIK_AUTO_CLEANUP', true);             // Auto cleanup expired bindings

// Default bandwidth limits (in kbps)
define('DEFAULT_DOWNLOAD_LIMIT', 2048);            // 2 Mbps download
define('DEFAULT_UPLOAD_LIMIT', 1024);              // 1 Mbps upload

// --- System Configuration ---
// The static IP address of this server PC.
// This is used to ignore the server's own MAC address.
define('SERVER_IP', '192.168.1.100');

// Set the default timezone for date/time functions
// --- Speed Tier Definitions ---
// Defines the available speed tiers for bundles.
define('SPEED_TIERS', [
    'bronze' => ['download_kbps' => 2048, 'upload_kbps' => 1024, 'label' => 'Bronze', 'color' => '#cd7f32'],
    'silver' => ['download_kbps' => 5120, 'upload_kbps' => 2048, 'label' => 'Silver', 'color' => '#c0c0c0'],
    'gold'   => ['download_kbps' => 10240, 'upload_kbps' => 5120, 'label' => 'Gold', 'color' => '#ffd700'],
]);

date_default_timezone_set('Africa/Nairobi');

// --- Start the session ---
// This is needed to track user login status.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

?>
