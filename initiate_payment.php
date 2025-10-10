<?php

// =================================================================
// --- PRODUCTION M-PESA PAYMENT LOGIC (DISABLED FOR TESTING) ---
// =================================================================
// The following code handles the M-Pesa STK push. It has been
// disabled to allow for immediate bundle activation for testing
// purposes. To re-enable for production, remove this entire comment
// block and delete the "TESTING LOGIC" block below.
// =================================================================
/*

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/mpesa_handler.php';

// --- Security & Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bundles.php');
    exit();
}

// Validate incoming data
$phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
$bundle_id = filter_input(INPUT_POST, 'bundle_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT);

if (!$bundle_id || !$amount || !$phone_number) {
    header('Location: bundles.php?status=error');
    exit();
}

// --- Create a Pending Transaction ---
$stmt = $mysqli->prepare(
    "INSERT INTO transactions (bundle_id, amount, phone_number, status) VALUES (?, ?, ?, 'pending')"
);
$stmt->bind_param('ids', $bundle_id, $amount, $phone_number);

if (!$stmt->execute()) {
    error_log('Failed to create pending transaction for phone: ' . $phone_number);
    header('Location: bundles.php?status=error');
    exit();
}
$transaction_id = $stmt->insert_id;

// --- Initiate STK Push ---
$account_reference = MPESA_ACCOUNT_NUMBER;
$response = initiate_stk_push($phone_number, $amount, $account_reference);

// --- Handle Response ---
if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
    $checkout_request_id = $response['CheckoutRequestID'];
    $update_stmt = $mysqli->prepare("UPDATE transactions SET mpesa_receipt_number = ? WHERE id = ?");
    $update_stmt->bind_param('si', $checkout_request_id, $transaction_id);
    $update_stmt->execute();

    header('Location: bundles.php?status=success');
    exit();
} else {
    $error_message = $response['errorMessage'] ?? 'Unknown error occurred.';
    error_log('M-Pesa STK Push failed: ' . $error_message);

    $fail_stmt = $mysqli->prepare("UPDATE transactions SET status = 'failed' WHERE id = ?");
    $fail_stmt->bind_param('i', $transaction_id);
    $fail_stmt->execute();

    header('Location: bundles.php?status=error');
    exit();
}

*/

// =================================================================
// --- TESTING LOGIC (IMMEDIATE BUNDLE ACTIVATION) ---
// =================================================================
// This code bypasses M-Pesa and activates the bundle directly.
// This should be removed when deploying to production.
// =================================================================

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/mikrotik_controller.php';

// Start session to access session variables like MAC address
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Security & Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bundles.php');
    exit();
}

$bundle_id = filter_input(INPUT_POST, 'bundle_id', FILTER_VALIDATE_INT);
$mac_address = $_SESSION['mac_address'] ?? null;

// Use a fake MAC for localhost testing if the session one isn't available
if (!$mac_address && ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1')) {
    $_SESSION['fake_mac_for_testing'] = $_SESSION['fake_mac_for_testing'] ?? '00:TEST:MAC:' . strtoupper(substr(md5(rand()), 0, 6));
    $mac_address = $_SESSION['fake_mac_for_testing'];
}

if (!$bundle_id || !$mac_address) {
    // Not enough info to proceed
    header('Location: bundles.php?status=error');
    exit();
}

// --- Activate Bundle Directly ---

// 1. Get bundle details from the database
$bundle_stmt = $mysqli->prepare("SELECT * FROM bundles WHERE id = ?");
$bundle_stmt->bind_param('i', $bundle_id);
$bundle_stmt->execute();
$bundle_result = $bundle_stmt->get_result();
$bundle = $bundle_result->fetch_assoc();

if (!$bundle) {
    // Bundle not found
    header('Location: bundles.php?status=error');
    exit();
}

// 2. Calculate expiry time
$duration_minutes = $bundle['duration_minutes'];
$start_time = date('Y-m-d H:i:s');
$expiry_time = date('Y-m-d H:i:s', strtotime(" +$duration_minutes minutes"));

// 3. Update or Insert device record in the database
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

// 4. Grant access on the MikroTik router
if (defined('MIKROTIK_ENABLED') && MIKROTIK_ENABLED) {
    $mikrotik = new MikroTikController();
    
    $bundle_data = $bundle;
    $bundle_data['bundle_start_time'] = $start_time;
    $bundle_data['bundle_expiry_time'] = $expiry_time;

    $mikrotik->allowMAC($mac_address, $bundle_data);
}

// 5. Redirect to the main page, which will now show the success message
header('Location: index.php');
exit();

?>