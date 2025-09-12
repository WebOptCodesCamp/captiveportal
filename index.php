<?php
// ----------------------------------------------------------------
// --- Captive Portal Entry Point ---
// ----------------------------------------------------------------

// This is the first page a user hits when connecting to the Wi-Fi.

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 1. Get the device's MAC address
$mac_address = get_mac_address();

// Store the MAC address in the session for later use (e.g., in the payment callback)
if ($mac_address) {
    $_SESSION['mac_address'] = $mac_address;
}

// 2. Check for an active bundle associated with this MAC address
$has_access = false;
if ($mac_address) {
    $has_access = has_active_bundle($mysqli, $mac_address);
}

// 3. Redirect or grant access
if ($has_access) {
    // --- ACCESS GRANTED ---
    // In a real system, the router's firewall/MAC filter would be updated
    // to allow this device's MAC address to access the internet.
    // For this simulation, we'll just show a success page.
    display_access_granted();
} else {
    // --- ACCESS DENIED ---
    // No active bundle found. Redirect to bundles page to purchase one.
    header('Location: bundles.php');
    exit();
}

/**
 * Displays the HTML page for when internet access is granted.
 */
function display_access_granted() {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Granted - WiFi Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="./tailwind.com"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="relative min-h-screen flex items-center justify-center">
    <div class="animated-bg"></div>
    
    <div class="text-center fade-in">
        <div class="glass-card p-10 max-w-md mx-auto">
            <!-- Success Animation -->
            <div class="inline-flex items-center justify-center w-24 h-24 mb-6 bg-gradient-to-br from-green-400 to-green-600 rounded-full success-animation">
                <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-800 mb-4">You're Connected!</h1>
            <p class="text-gray-600 text-lg mb-6">Your device has an active data bundle. Enjoy high-speed internet access.</p>
            
            <!-- Connection Status -->
            <div class="bg-green-50 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-green-700 font-medium">Active Connection</span>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="space-y-3">
                <a href="https://www.google.com" target="_blank" class="btn btn-primary w-full">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                    </svg>
                    <span>Browse the Web</span>
                </a>
                
                <button onclick="window.location.reload()" class="btn btn-secondary w-full">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Check Status</span>
                </button>
            </div>
            
            <!-- Support Info -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    Need help? Contact support at <a href="tel:+254700000000" class="text-indigo-600 hover:text-indigo-500 font-medium">0700 000 000</a>
                </p>
            </div>
        </div>
        
        <!-- Decorative Elements -->
        <div class="mt-8 flex justify-center space-x-2">
            <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0s"></div>
            <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
            <div class="w-2 h-2 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
        </div>
    </div>
</body>
</html>
<?php
}
?>
