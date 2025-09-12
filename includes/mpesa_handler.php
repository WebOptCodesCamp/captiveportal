<?php
// ----------------------------------------------------------------
// --- M-Pesa Daraja API Handler ---
// ----------------------------------------------------------------

require_once 'config.php';

/**
 * Gets an OAuth 2.0 access token from the Safaricom API.
 *
 * @return string|null The access token or null on failure.
 */
function get_mpesa_access_token() {
    $url = (MPESA_ENV === 'sandbox')
        ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response);
    return $data->access_token ?? null;
}

/**
 * Initiates an STK Push (Lipa Na M-Pesa Online) request.
 *
 * @param string $phone_number The customer's phone number (e.g., 2547xxxxxxxx).
 * @param int $amount The amount to be paid.
 * @param string $account_reference A reference for the transaction.
 * @return array|null The decoded JSON response from Safaricom or null on failure.
 */
function initiate_stk_push($phone_number, $amount, $account_reference = 'WifiBundle') {
    $access_token = get_mpesa_access_token();
    if (!$access_token) {
        return null;
    }

    $url = (MPESA_ENV === 'sandbox')
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

    // Format the phone number to Safaricom's required format (254...)
    $formatted_phone = preg_replace('/^0/', '254', $phone_number);

    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline', // or 'CustomerBuyGoodsOnline'
        'Amount' => $amount,
        'PartyA' => $formatted_phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $formatted_phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $account_reference,
        'TransactionDesc' => 'Payment for WiFi data bundle'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
?>
