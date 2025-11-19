<?php
/* SavPay WHMCS Plugin
 * Author: Sav Technologies
 * Developer: Imam Hasan Emon
 * Assist: Sav Developer Team
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Config\Setting;

// GET VALUES FROM URL
$invoiceId      = isset($_REQUEST['invoice']) ? $_REQUEST['invoice'] : null;
$transactionId  = isset($_REQUEST['transactionId']) ? $_REQUEST['transactionId'] : null;
$paymentAmount  = isset($_REQUEST['paymentAmount']) ? $_REQUEST['paymentAmount'] : 0;
$paymentFee     = isset($_REQUEST['paymentFee']) ? $_REQUEST['paymentFee'] : 0;

$apikey = isset($_GET['api']) ? $_GET['api'] : null;

if (!$invoiceId || !$transactionId || !$apikey) {
    echo "Invalid Callback Request";
    exit();
}

/* ----------------------------------------------------
 * VERIFY PAYMENT WITH SAVPAY API
 * ---------------------------------------------------- */

$data = json_encode([
    "transaction_id" => $transactionId
]);

$headers = [
    'Content-Type: application/json',
    'API-KEY: ' . $apikey
];

$url = 'https://pay.sav.com.bd/api/payment/verify';

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => $headers
]);

$response = curl_exec($curl);
curl_close($curl);

$result = json_decode($response, true);

/* ----------------------------------------------------
 * CHECK VERIFY RESULT AND MARK INVOICE PAID
 * ---------------------------------------------------- */

// SavPay returns: { "status": true, "message": "...", ... }

if (isset($result['status']) && $result['status'] === true) {

    // Mark Invoice as PAID
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        "savpay"
    );

    // Redirect to clean invoice page
    $systemUrl = Setting::getValue('SystemURL');
    header("Location: " . $systemUrl . "/viewinvoice.php?id=" . $invoiceId);
    exit();

} else {

    echo "Payment Verification Failed";
}

?>
