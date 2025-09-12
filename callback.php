<?php
// ----------------------------------------------------------------
// --- M-Pesa Callback Handler ---
// ----------------------------------------------------------------

// This script is called by the Safaricom API to notify us of the payment result.
// It MUST be publicly accessible.

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php'; // For get_mac_address

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
            $bundle_stmt = $mysqli->prepare("SELECT data_limit_mb, duration_minutes FROM bundles WHERE id = ?");
            $bundle_stmt->bind_param('i', $bundle_id);
            $bundle_stmt->execute();
            $bundle_result = $bundle_stmt->get_result();
            $bundle = $bundle_result->fetch_assoc();

            if ($bundle) {
                // The MAC address should have been stored in the session when the user first hit index.php.
                if (isset($_SESSION['mac_address'])) {
                    $mac_address = $_SESSION['mac_address'];

                    $duration_minutes = $bundle['duration_minutes'];
                    $start_time = date('Y-m-d H:i:s');
                    $expiry_time = date('Y-m-d H:i:s', strtotime(" +$duration_minutes minutes"));

                    // Use INSERT ... ON DUPLICATE KEY UPDATE to either create or update the device record.
                    // The `mac_address` column has a UNIQUE constraint.
                    $device_stmt = $mysqli->prepare(
                        "INSERT INTO devices (user_id, mac_address, bundle_id, data_used_mb, bundle_start_time, bundle_expiry_time) 
                         VALUES (?, ?, ?, 0.00, ?, ?) 
                         ON DUPLICATE KEY UPDATE
                         user_id = VALUES(user_id),
                         bundle_id = VALUES(bundle_id),
                         data_used_mb = 0.00,
                         bundle_start_time = VALUES(bundle_start_time),
                         bundle_expiry_time = VALUES(bundle_expiry_time)"
                    );
                    $device_stmt->bind_param('isiss', $user_id, $mac_address, $bundle_id, $start_time, $expiry_time);
                    $device_stmt->execute();
                } else {
                    // If the MAC is not in the session, we cannot activate the bundle directly.
                    // Log this event. The user will need to reconnect to the portal.
                    // When they reconnect, index.php will find their completed transaction and activate the bundle then.
                    file_put_contents('callback_log.txt', "Warning: MAC address not found in session. Bundle activation will occur on next connection.\n", FILE_APPEND);
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