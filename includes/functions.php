<?php
// ----------------------------------------------------------------
// --- Core Functions ---
// ----------------------------------------------------------------

require_once 'db.php';

/**
 * Gets the MAC address of a client based on their IP address by executing `arp -a`.
 *
 * This function is designed for a Windows environment. It captures the client's IP,
 * runs the ARP command, and parses the output to find the corresponding MAC address.
 *
 * @return string|null The MAC address in XX-XX-XX-XX-XX-XX format, or null if not found.
 */
function get_mac_address() {
    // Get the client's IP address.
    // REMOTE_ADDR is generally reliable, but we check others as a fallback.
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP']);

    // Ignore the server's own IP to prevent it from locking itself out.
    if ($ip_address == SERVER_IP) {
        return null;
    }

    try {
        // Execute the `arp -a` command. The output is captured.
        // `@` suppresses errors if the command fails.
        $arp_output = @shell_exec('arp -a ' . escapeshellarg($ip_address));

        // If the command fails or returns no output, return null.
        if (empty($arp_output)) {
            return null;
        }

        // Search for the MAC address in the output.
        // The regex looks for a pattern of 6 pairs of hex characters (0-9, a-f),
        // separated by hyphens or colons. It's case-insensitive.
        if (preg_match('/([0-9a-f]{2}[:-]){5}([0-9a-f]{2})/i', $arp_output, $matches)) {
            // A MAC address was found.
            // Standardize the format to use hyphens and uppercase.
            $mac_address = strtoupper(str_replace(':', '-', $matches[0]));
            return $mac_address;
        }
    } catch (Exception $e) {
        // In case of any exception (e.g., shell_exec is disabled), return null.
        // You might want to log this error in a real production environment.
        error_log('Error in get_mac_address(): ' . $e->getMessage());
        return null;
    }

    // Return null if no MAC address was found.
    return null;
}

/**
 * Checks if a device has an active and valid data bundle.
 *
 * @param mysqli $mysqli The database connection object.
 * @param string $mac_address The MAC address of the device to check.
 * @return bool True if the bundle is active, false otherwise.
 */
function has_active_bundle($mysqli, $mac_address) {
    // Your existing database check
    $has_bundle = check_database_bundle($mysqli, $mac_address);
    
    if ($has_bundle) {
        // Allow access via MikroTik API
        $mikrotik = new MikroTikAPI(MIKROTIK_HOST, MIKROTIK_USER, MIKROTIK_PASS);
        $mikrotik->allowUser($mac_address);
    } else {
        // Block access via MikroTik API
        $mikrotik = new MikroTikAPI(MIKROTIK_HOST, MIKROTIK_USER, MIKROTIK_PASS);
        $mikrotik->blockUser($mac_address);
    }
    
    return $has_bundle;
}

?>
