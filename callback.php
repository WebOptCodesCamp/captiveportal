<?php
// ----------------------------------------------------------------
// --- M-Pesa Callback Handler ---
// ----------------------------------------------------------------

// This script is called by the Safaricom API to notify us of the payment result.
// It MUST be publicly accessible.

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php'; // For get_mac_address
require_once 'includes/mikrotik_controller.php';

// --- Log the Raw Input ---
// For debugging purposes, it's crucial to log the raw callback data.
$callback_data_raw = file_get_contents('php://input');
file_put_contents('callback_log.txt', $callback_data_raw . PHP_EOL, FILE_APPEND);

$callback_data = json_decode($callback_data_raw, true);

// --- Process the Callback ---
if (isset($callback_data['Body']['stkCallback'])) {
    $stk_callback = $callback_data['Body']['stkCallback'];
    $result_code = $stk_callback['ResultCode'];
    $checkout_request_id = $stk_callback['CheckoutRequestID'];

    // Find the transaction using the CheckoutRequestID
    $stmt = $mysqli->prepare("SELECT id, bundle_id FROM transactions WHERE mpesa_receipt_number = ? AND status = 'pending'");
    $stmt->bind_param('s', $checkout_request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($transaction = $result->fetch_assoc()) {
        $transaction_id = $transaction['id'];
        $bundle_id = $transaction['bundle_id'];

        if ($result_code == 0) {
            // --- PAYMENT SUCCESSFUL ---
            $callback_metadata = $stk_callback['CallbackMetadata']['Item'];
            $mpesa_receipt = '';
            foreach ($callback_metadata as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $mpesa_receipt = $item['Value'];
                    break;
                }
            }

            // 1. Update the transaction to 'completed'
            $update_stmt = $mysqli->prepare("UPDATE transactions SET status = 'completed', mpesa_receipt_number = ? WHERE id = ?");
            $update_stmt->bind_param('si', $mpesa_receipt, $transaction_id);
            $update_stmt->execute();

            // 2. Activate the data bundle for the user's device
            // First, get the bundle details
            $bundle_stmt = $mysqli->prepare("SELECT * FROM bundles WHERE id = ?");
            $bundle_stmt->bind_param('i', $bundle_id);
            $bundle_stmt->execute();
            $bundle_result = $bundle_stmt->get_result();
            $bundle = $bundle_result->fetch_assoc();

            if ($bundle) {
                // Get MAC address from session or transaction
                $mac_address = $_SESSION['mac_address'] ?? null;
                
                // If MAC not in session, try to get from recent transactions
                if (!$mac_address) {
                    $phone_stmt = $mysqli->prepare("SELECT phone_number FROM transactions WHERE id = ?");
                    $phone_stmt->bind_param('i', $transaction_id);
                    $phone_stmt->execute();
                    $phone_result = $phone_stmt->get_result();
                    $phone_data = $phone_result->fetch_assoc();
                    
                    if ($phone_data) {
                        // Try to find MAC from recent connections with this phone
                        $mac_stmt = $mysqli->prepare("
                            SELECT mac_address FROM devices d 
                            JOIN transactions t ON d.bundle_id = t.bundle_id 
                            WHERE t.phone_number = ? 
                            ORDER BY d.last_seen DESC LIMIT 1
                        ");
                        $mac_stmt->bind_param('s', $phone_data['phone_number']);
                        $mac_stmt->execute();
                        $mac_result = $mac_stmt->get_result();
                        $mac_data = $mac_result->fetch_assoc();
                        $mac_address = $mac_data['mac_address'] ?? null;
                    }
                }

                if ($mac_address) {
                    $duration_minutes = $bundle['duration_minutes'];
                    $start_time = date('Y-m-d H:i:s');
                    $expiry_time = date('Y-m-d H:i:s', strtotime(" +$duration_minutes minutes"));

                    // Update/Insert device record
                    $device_stmt = $mysqli->prepare(
                        "INSERT INTO devices (mac_address, bundle_id, data_used_mb, bundle_start_time, bundle_expiry_time) 
                         VALUES (?, ?, 0.00, ?, ?) 
                         ON DUPLICATE KEY UPDATE
                         bundle_id = VALUES(bundle_id),
                         data_used_mb = 0.00,
                         bundle_start_time = VALUES(bundle_start_time),
                         bundle_expiry_time = VALUES(bundle_expiry_time)"
                    );
                    $device_stmt->bind_param('siss', $mac_address, $bundle_id, $start_time, $expiry_time);
                    $device_stmt->execute();

                    // IMMEDIATELY GRANT ACCESS VIA MIKROTIK
                    if (defined('MIKROTIK_ENABLED') && MIKROTIK_ENABLED) {
                        $mikrotik = new MikroTikController();
                        
                        // Prepare comprehensive bundle data for MikroTik
                        $bundle_data = array(
                            'name' => $bundle['name'],
                            'data_limit_mb' => $bundle['data_limit_mb'],
                            'duration_minutes' => $bundle['duration_minutes'],
                            'is_unlimited' => $bundle['is_unlimited'],
                            'bundle_expiry_time' => $expiry_time,
                            'bundle_start_time' => $start_time
                        );
                        
                        // Add speed limits based on bundle type
                        if (defined('MIKROTIK_BANDWIDTH_LIMITING') && MIKROTIK_BANDWIDTH_LIMITING) {
                            if ($bundle['is_unlimited']) {
                                // Premium speeds for unlimited bundles
                                $bundle_data['download_limit_kbps'] = $bundle['download_limit_kbps'] ?? (DEFAULT_DOWNLOAD_LIMIT * 2);
                                $bundle_data['upload_limit_kbps'] = $bundle['upload_limit_kbps'] ?? (DEFAULT_UPLOAD_LIMIT * 2);
                            } else {
                                // Standard speeds for limited bundles
                                $bundle_data['download_limit_kbps'] = $bundle['download_limit_kbps'] ?? DEFAULT_DOWNLOAD_LIMIT;
                                $bundle_data['upload_limit_kbps'] = $bundle['upload_limit_kbps'] ?? DEFAULT_UPLOAD_LIMIT;
                            }
                        }
                        
                        $success = $mikrotik->allowMAC($mac_address, $bundle_data);
                        
                        if ($success) {
                            $bundle_type = $bundle['is_unlimited'] ? 'UNLIMITED' : $bundle['data_limit_mb'] . 'MB LIMITED';
                            file_put_contents('callback_log.txt', 
                                "SUCCESS: MikroTik access granted to MAC {$mac_address} - Bundle: {$bundle['name']} ({$bundle_type}) - Expires: {$expiry_time}\n", 
                                FILE_APPEND
                            );
                        } else {
                            file_put_contents('callback_log.txt', 
                                "ERROR: Failed to grant MikroTik access to MAC {$mac_address} - Bundle: {$bundle['name']}\n", 
                                FILE_APPEND
                            );
                        }
                    }
                } else {
                    file_put_contents('callback_log.txt', 
                        "Warning: MAC address not found for transaction {$transaction_id}. Bundle activation will occur on next connection.\n", 
                        FILE_APPEND
                    );
                }
            }

        } else {
            // --- PAYMENT FAILED or CANCELLED ---
            $update_stmt = $mysqli->prepare("UPDATE transactions SET status = 'failed' WHERE id = ?");
            $update_stmt->bind_param('i', $transaction_id);
            $update_stmt->execute();
        }
    }
}

// --- Respond to Safaricom ---
// It's good practice to send a response to acknowledge receipt of the callback.
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

?>