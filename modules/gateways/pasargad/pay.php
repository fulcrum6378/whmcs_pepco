<?php /** @noinspection PhpUndefinedVariableInspection */

include "./includes/shared.php";

# Input Data
$invoiceId = $_POST['invoice_id'];
$amount = intval($_POST['amount']);

# Token Request
$tokenReq = PepTokenRequest();
if (isset($tokenReq) && $tokenReq->resultCode == 0)
    $token = $tokenReq->token;
else {
    error($tokenReq, 'خطا در دریافت توکن');
    exit();
}

# Purchase Request
$purchase = PepPurchaseRequest(
    $token, $invoiceId . mt_rand(10, 100), $amount,
    $WHMCS_URL . '/modules/gateways/pasargad/callback.php?amount=' . $amount . '&invoice_id=' . $invoiceId);

if (isset($purchase) && $purchase->resultCode == 0)
    redirect($PEP_BASE_URL . '/' . $purchase->data->urlId);
else
    error($purchase, 'خطا در ارسال به بانک');


/** Retrieves a token.
 * Test via PowerShell:
 * $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
 * Invoke-WebRequest -UseBasicParsing -Uri "https://pep.shaparak.ir/dorsa1/token/getToken" `
 * -Method "POST" `
 * -WebSession $session `
 * -ContentType "application/json" `
 * -Body '{"username": "<USERNAME>", "password": "<PASSWORD>"}'
 */
function PepTokenRequest() {
    global $GATEWAY, $PEP_BASE_URL;
    require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
    $processor = new RSAProcessor(
        dirname(__FILE__) . '/includes/certificate.xml',
        RSAKeyType::XMLFile
    );

    $data = array(
        'username' => $GATEWAY['Username'],
        'password' => $GATEWAY['Password'],
    );

    $sign_data = json_encode($data);
    $sign_data = sha1($sign_data, true);
    $sign_data = $processor->sign($sign_data);
    $sign = base64_encode($sign_data);

    $curl = curl_init($PEP_BASE_URL . '/token/getToken');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Sign: ' . $sign
        )
    );
    $result = json_decode(curl_exec($curl));
    curl_close($curl);
    return $result;
}

function PepPurchaseRequest($token, $invoice, $amount, $callbackUrl) {
    global $GATEWAY, $PEP_BASE_URL;
    require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
    $processor = new RSAProcessor(
        dirname(__FILE__) . '/includes/certificate.xml',
        RSAKeyType::XMLFile
    );

    $data = array(
        'amount' => $amount,
        'callbackApi' => $callbackUrl,
        'description' => '',
        'invoice' => $invoice,
        'invoiceDate' => date('Y-m-d'),
        'mobileNumber' => '',
        'payerMail' => '',
        'payerName' => '',
        'serviceCode' => 8,
        'serviceType' => 'PURCHASE',
        'terminalNumber' => $GATEWAY['TerminalNumber'],
        'nationalCode' => '',
        'pans' => '',
    );

    $sign_data = json_encode($data);
    $sign_data = sha1($sign_data, true);
    $sign_data = $processor->sign($sign_data);
    $sign = base64_encode($sign_data);

    $curl = curl_init($PEP_BASE_URL . '/api/payment/purchase');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Sign: ' . $sign,
            'Authorization: Bearer ' . $token,
        )
    );
    $result = json_decode(curl_exec($curl));
    curl_close($curl);
    return $result;
}

function redirect($url) {
    if ($url == '') return;
    if (headers_sent())
        echo '<script type="text/javascript">window.location.assign("' . $url . '");</script>';
    else
        header("Location: $url");
    exit();
}

function error($req, $title) {
    global $CONFIG, $invoiceId;
    echo '<!DOCTYPE html> 
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>' . $title . '</title>
<style>
body {
    font-family: tahoma, serif;
    text-align: center;
    margin-top: 30px;
}
main {
    font-family: tahoma, serif;
    font-size: 12px;
    border: 1px dotted #c3c3c3;
    width: 60%;
    margin: 50px auto 0 auto;
    line-height: 25px;
    padding-left: 12px;
    padding-top: 8px;
}
</style>
</head>

<body>
	<main>
		<span style="color: #FF0000;"><b>' . $title . '</b></span><br>
		<p style="text-align: center;">' . $req->resultMsg ?? 'خطای نامشخص' . '</p>
		<a href="' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceId . '">بازگشت >></a>
		<br><br>
	</main>
</body>
</html>';
}
