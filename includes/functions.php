<?php
// ----------------------------------------------------------------
// --- Core Functions ---
// ----------------------------------------------------------------

require_once 'db.php';
require_once 'mikrotik_controller.php';

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
 * Enhanced bundle check with MikroTik integration
 */
function has_active_bundle($mysqli, $mac_address) {
    // Get detailed bundle information
    $stmt = $mysqli->prepare("
        SELECT d.*, b.name, b.data_limit_mb, b.duration_minutes, b.price_kes, b.is_unlimited,
               CASE 
                   WHEN b.is_unlimited = 1 THEN 'unlimited'
                   WHEN d.data_used_mb >= b.data_limit_mb THEN 'data_exhausted'
                   WHEN d.bundle_expiry_time <= NOW() THEN 'time_expired'
                   ELSE 'active'
               END as bundle_status
        FROM devices d 
        LEFT JOIN bundles b ON d.bundle_id = b.id 
        WHERE d.mac_address = ?
    ");
    $stmt->bind_param('s', $mac_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $bundle_data = $result->fetch_assoc();
    
    if ($bundle_data) {
        $is_active = false;
        
        // Check if bundle is still valid
        if ($bundle_data['bundle_status'] === 'active' || $bundle_data['bundle_status'] === 'unlimited') {
            // For unlimited bundles, only check time expiry
            if ($bundle_data['is_unlimited'] == 1) {
                $is_active = strtotime($bundle_data['bundle_expiry_time']) > time();
            } 
            // For limited bundles, check both time and data
            else {
                $time_valid = strtotime($bundle_data['bundle_expiry_time']) > time();
                $data_valid = $bundle_data['data_used_mb'] < $bundle_data['data_limit_mb'];
                $is_active = $time_valid && $data_valid;
            }
        }
        
        if ($is_active) {
            // Grant access via MikroTik
            if (defined('MIKROTIK_ENABLED') && MIKROTIK_ENABLED) {
                $mikrotik = new MikroTikController();
                $success = $mikrotik->allowMAC($mac_address, $bundle_data);
                
                if ($success) {
                    error_log("MikroTik: Access granted to {$mac_address} - Bundle: {$bundle_data['name']} (" . 
                             ($bundle_data['is_unlimited'] ? 'UNLIMITED' : $bundle_data['data_limit_mb'] . 'MB') . ")");
                }
            }
            return true;
        } else {
            // Block access and clean up MikroTik
            if (defined('MIKROTIK_ENABLED') && MIKROTIK_ENABLED) {
                $mikrotik = new MikroTikController();
                $mikrotik->blockMAC($mac_address);
                
                error_log("MikroTik: Access blocked for {$mac_address} - Reason: {$bundle_data['bundle_status']}");
            }
            return false;
        }
    } else {
        // No bundle found - block access
        if (defined('MIKROTIK_ENABLED') && MIKROTIK_ENABLED) {
            $mikrotik = new MikroTikController();
            $mikrotik->blockMAC($mac_address);
        }
        return false;
    }
}

/**
 * Check database for active bundle
 */
function check_database_bundle($mysqli, $mac_address) {
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as has_bundle 
        FROM devices 
        WHERE mac_address = ? AND bundle_expiry_time > NOW()
    ");
    $stmt->bind_param('s', $mac_address);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['has_bundle'] > 0;
}

?>
