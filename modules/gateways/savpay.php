<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/* -------------------------------------------------------
 * GLOBAL FIX — ALWAYS VERIFY WHEN RETURNING FROM SAVPAY
 * ------------------------------------------------------- */

if (isset($_GET['transactionId']) && isset($_GET['status']) && isset($_GET['id'])) {

    require_once dirname(__DIR__, 2) . '/init.php';
    require_once dirname(__DIR__, 2) . '/includes/gatewayfunctions.php';
    require_once dirname(__DIR__, 2) . '/includes/invoicefunctions.php';

    $params = getGatewayVariables('savpay');

    $invoiceid      = $_GET['id'];
    $transactionId  = $_GET['transactionId'];
    $paymentAmount  = isset($_GET['paymentAmount']) ? $_GET['paymentAmount'] : 0;
    $paymentFee     = isset($_GET['paymentFee']) ? $_GET['paymentFee'] : 0;
    $status         = $_GET['status'];

    if ($status === "completed") {

        $verify = savpay_verify_transaction($transactionId, $params);

        if ($verify === true) {

            addInvoicePayment(
                $invoiceid,
                $transactionId,
                $paymentAmount,
                $paymentFee,
                $params['paymentmethod']
            );

            header("Location: viewinvoice.php?id=" . $invoiceid);
            exit;
        }
    }

    header("Location: viewinvoice.php?id=" . $invoiceid);
    exit;
}

/* -------------------------------------------------------
 * MODULE METADATA
 * ------------------------------------------------------- */

function savpay_MetaData()
{
    return array(
        'DisplayName' => 'savpay',
        'APIVersion' => '1.0',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
        
        'Description' => 'Pay with Bkash, Rocket, Nagad, Tap All MFS and Bank Payment via savpay',
        'GatewayType' => 'Payment Gateway', 
        'Author' => 'Sav Technologies',
        'Language' => 'english',
        'Website' => 'https://sav.com.bd/whmcs-module',
        'SupportEmail' => 'support@sav.com.bd',
    );
}

/* -------------------------------------------------------
 * FRONTEND PAY BUTTON HANDLER
 * ------------------------------------------------------- */

function savpay_link($params)
{
    $host_config = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $host_config = pathinfo($host_config, PATHINFO_FILENAME);

    if (isset($_POST['pay'])) {
        $response = savpay_payment_url($params);
        if ($response->status) {
            return '<form action="' . $response->payment_url . '" method="GET">
                <input class="btn btn-primary" type="submit" value="' . $params['langpaynow'] . '" />
            </form>';
        }
        return $response->message;
    }

    if ($host_config == "viewinvoice") {
        return '<form action="" method="POST">
            <input class="btn btn-primary" name="pay" type="submit" value="' . $params['langpaynow'] . '" />
        </form>';
    } else {
        $response = savpay_payment_url($params);
        if ($response->status) {
            return '<form action="' . $response->payment_url . '" method="GET">
                <input class="btn btn-primary" type="submit" value="' . $params['langpaynow'] . '" />
            </form>';
        }
        return $response->message;
    }
}

/* -------------------------------------------------------
 * MODULE SETTINGS FORM
 * ------------------------------------------------------- */

function savpay_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Bkash ,Rocket , Nagad All MFS and Bank Payment',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '150',
            'Description' => 'Enter Your SavPay API Key',
        ),
        'currency_rate' => array(
            'FriendlyName' => 'Currency Rate',
            'Type' => 'text',
            'Size' => '150',
            'Default' => '120',
            'Description' => 'Enter Dollar Rate',
        )
    );
}

/* -------------------------------------------------------
 * CREATE PAYMENT URL
 * ------------------------------------------------------- */

function savpay_payment_url($params)
{
    $cus_name  = $params['clientdetails']['firstname'] . " " . $params['clientdetails']['lastname'];
    $cus_email = $params['clientdetails']['email'];
    $apikey    = $params['apiKey'];
    $invoiceId = $params['invoiceid'];

    $systemUrl = rtrim($params['systemurl'], '/'); // FIX FOR /my/

    $amount = ($params['currency'] == "USD") ?
                $params['amount'] * $params['currency_rate'] :
                $params['amount'];

    $success_url = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;
    $cancel_url  = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;
    $webhook_url = $systemUrl . '/modules/gateways/callback/savpay.php?api=' . $apikey . '&invoice=' . $invoiceId;

    $data = array(
        "cus_name"    => $cus_name,
        "cus_email"   => $cus_email,
        "amount"      => $amount,
        "webhook_url" => $webhook_url,
        "success_url" => $success_url,
        "cancel_url"  => $cancel_url,
    );

    $headers = array(
        'Content-Type: application/json',
        'API-KEY: ' . $apikey,
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://pay.sav.com.bd/api/payment/create',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response);
}

/* -------------------------------------------------------
 * VERIFY TRANSACTION
 * ------------------------------------------------------- */

function savpay_verify_transaction($transactionId, $params)
{
    $apikey = $params['apiKey'];

    $payload = json_encode([
        "transaction_id" => $transactionId
    ]);

    $headers = [
        "Content-Type: application/json",
        "API-KEY: " . $apikey
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://pay.sav.com.bd/api/payment/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $res = json_decode($response, true);

    // SavPay returns status = "COMPLETED"
    return (isset($res['status']) && strtoupper($res['status']) === "COMPLETED");
}


?>
