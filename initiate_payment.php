<?php
// ----------------------------------------------------------------
// --- Initiate M-Pesa STK Push ---
// ----------------------------------------------------------------

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/mpesa_handler.php';

// --- Security & Validation ---
// The request must be a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bundles.php');
    exit();
}

// Validate incoming data
$phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
$bundle_id = filter_input(INPUT_POST, 'bundle_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT);

if (!$bundle_id || !$amount || !$phone_number) {
    // If data is invalid, redirect back with an error
    header('Location: bundles.php?status=error');
    exit();
}


// --- Create a Pending Transaction ---
// This helps in tracking the payment process. The callback will update this record.
$stmt = $mysqli->prepare(
    "INSERT INTO transactions (bundle_id, amount, phone_number, status) VALUES (?, ?, ?, 'pending')"
);
$stmt->bind_param('ids', $bundle_id, $amount, $phone_number);

if (!$stmt->execute()) {
    // If database insertion fails, we cannot proceed.
    error_log('Failed to create pending transaction for phone: ' . $phone_number);
    header('Location: bundles.php?status=error');
    exit();
}
$transaction_id = $stmt->insert_id; // We might need this for reference later


// --- Initiate STK Push ---
$account_reference = MPESA_ACCOUNT_NUMBER; // Use the account number from config
$response = initiate_stk_push($phone_number, $amount, $account_reference);


// --- Handle Response ---
if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
    // The request was accepted by Safaricom for processing.
    // The actual payment result will be sent to our callback URL.

    // We can store the CheckoutRequestID to reconcile the transaction later if needed.
    $checkout_request_id = $response['CheckoutRequestID'];
    $update_stmt = $mysqli->prepare("UPDATE transactions SET mpesa_receipt_number = ? WHERE id = ?");
    $update_stmt->bind_param('si', $checkout_request_id, $transaction_id);
    $update_stmt->execute();


    // Redirect back to the bundles page with a success message.
    header('Location: bundles.php?status=success');
    exit();
} else {
    // The request failed.
    // Log the error and update the transaction status to 'failed'.
    $error_message = $response['errorMessage'] ?? 'Unknown error occurred.';
    error_log('M-Pesa STK Push failed: ' . $error_message);

    $fail_stmt = $mysqli->prepare("UPDATE transactions SET status = 'failed' WHERE id = ?");
    $fail_stmt->bind_param('i', $transaction_id);
    $fail_stmt->execute();

    // Redirect back with a generic error message.
    header('Location: bundles.php?status=error');
    exit();
}
?>
