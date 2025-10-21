<?php
// ----------------------------------------------------------------
// --- Data Usage Synchronization Script ---
// ----------------------------------------------------------------

// This script should be run periodically (e.g., via a cron job) to 
// synchronize data usage from the MikroTik router to the local database.

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/mikrotik_controller.php';

// --- Logging Setup ---
$log_file = 'data_sync.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

log_message('--- Starting data usage synchronization and cleanup ---');

// --- Main Sync Logic ---
$mikrotik = new MikroTikController();

// --- ADDED: Clean up expired IP bindings first ---
log_message('Running cleanup of expired bindings...');
$cleaned_count = $mikrotik->cleanupExpiredBindings();
log_message("Cleanup finished. Removed {$cleaned_count} expired bindings.");
// --- END of ADDED section ---

// 1. Get all simple queues with their statistics from MikroTik
$queues = $mikrotik->getQueuesWithStats();

if (empty($queues)) {
    log_message('No captive portal queues found on MikroTik. Exiting.');
    exit;
}

log_message('Found ' . count($queues) . ' queues to process.');

// 2. Prepare database statements for updating and checking
$update_stmt = $mysqli->prepare("UPDATE devices SET data_used_mb = ? WHERE mac_address = ?");
$check_stmt = $mysqli->prepare("
    SELECT d.data_used_mb, b.data_limit_mb, b.is_unlimited 
    FROM devices d 
    JOIN bundles b ON d.bundle_id = b.id 
    WHERE d.mac_address = ?
");

if (!$update_stmt || !$check_stmt) {
    log_message('Failed to prepare database statements: ' . $mysqli->error);
    exit;
}

$update_count = 0;

// 3. Iterate through queues, update the database, and check for depletion
foreach ($queues as $queue) {
    // Extract MAC address from the queue name (e.g., 'captive_00-11-22-33-44-55')
    $mac_address = str_replace(['_', '-'], ':', substr($queue['name'], 8)); // Assumes 'captive_' prefix
    $mac_address = strtoupper($mac_address);

    // The stats are provided in 'bytes' as 'upload-bytes/download-bytes'
    if (isset($queue['bytes'])) {
        list($upload_bytes, $download_bytes) = explode('/', $queue['bytes']);
        $total_bytes = $upload_bytes + $download_bytes;
        $total_mb = round($total_bytes / 1024 / 1024, 2);

        // Bind parameters and execute the update
        $update_stmt->bind_param('ds', $total_mb, $mac_address);
        if ($update_stmt->execute()) {
            if ($update_stmt->affected_rows > 0) {
                log_message("Updated MAC: {$mac_address} - Usage: {$total_mb} MB");
                $update_count++;

                // --- Check if the device has exceeded its data limit ---
                $check_stmt->bind_param('s', $mac_address);
                if ($check_stmt->execute()) {
                    $result = $check_stmt->get_result();
                    if ($device_data = $result->fetch_assoc()) {
                        $is_unlimited = $device_data['is_unlimited'];
                        $data_used = $device_data['data_used_mb'];
                        $data_limit = $device_data['data_limit_mb'];

                        if (!$is_unlimited && $data_limit > 0 && $data_used >= $data_limit) {
                            log_message("DATA DEPLETED: MAC: {$mac_address} used {$data_used}MB of {$data_limit}MB limit. Blocking device.");
                            $mikrotik->blockMAC($mac_address);
                        }
                    }
                } else {
                    log_message("ERROR: Failed to check bundle limit for MAC {$mac_address}: " . $check_stmt->error);
                }
            }
        } else {
            log_message("ERROR: Failed to update MAC {$mac_address}: " . $update_stmt->error);
        }
    }
}

$update_stmt->close();
$check_stmt->close();
$mysqli->close();

log_message("--- Synchronization complete. Updated {$update_count} devices. ---");

?>
