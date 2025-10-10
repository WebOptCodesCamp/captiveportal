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

// 2. Prepare the database statement for updating
$stmt = $mysqli->prepare("UPDATE devices SET data_used_mb = ? WHERE mac_address = ?");

if (!$stmt) {
    log_message('Failed to prepare database statement: ' . $mysqli->error);
    exit;
}

$update_count = 0;

// 3. Iterate through queues and update the database
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
        $stmt->bind_param('ds', $total_mb, $mac_address);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                log_message("Updated MAC: {$mac_address} - Usage: {$total_mb} MB");
                $update_count++;
            }
        } else {
            log_message("ERROR: Failed to update MAC {$mac_address}: " . $stmt->error);
        }
    }
}

$stmt->close();
$mysqli->close();

log_message("--- Synchronization complete. Updated {$update_count} devices. ---");

?>
