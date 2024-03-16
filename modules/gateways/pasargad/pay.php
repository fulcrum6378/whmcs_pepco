<?php /** @noinspection PhpUndefinedVariableInspection */

include "./includes/shared.php";

# process the POST parameters
if (empty($_POST['invoice_id']) || empty($_POST['amount']))
    error((object)['resultMsg' =>
        'Parameters { ' .
        (empty($_POST['invoice_id']) ? 'invoice_id, ' : '') .
        (empty($_POST['amount']) ? 'amount, ' : '') .
        ' } are missing.'
    ], 'خطا در پارامتر های ورودی');
$invoiceId = $_POST['invoice_id'];
$amount = intval($_POST['amount']);

# get a token for interacting with API
$tokenReq = PepGetToken();
if (isset($tokenReq) && $tokenReq->resultCode == 0)
    $token = $tokenReq->token;
else
    error($tokenReq, 'خطا در دریافت توکن');

# send the purchase request and get a URL
$purchase = PepPurchase(
    $token, $invoiceId . mt_rand(10, 100), $amount,
    $WHMCS_URL . '/modules/gateways/callback/pasargad.php');

# redirect to the received token-like URL (different from the API token)
if (isset($purchase) && $purchase->resultCode == 0)
    redirect(PEP_BASE_URL . '/' . $purchase->data->urlId);
else
    error($purchase, 'خطا در ارسال به بانک');


/** Retrieves a token for future interactions with the API.
 * Test via PowerShell:
 * $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
 * Invoke-WebRequest -UseBasicParsing -Uri "https://pep.shaparak.ir/dorsa1/token/getToken" `
 * -Method "POST" `
 * -WebSession $session `
 * -ContentType "application/json" `
 * -Body '{"username": "<USERNAME>", "password": "<PASSWORD>"}'
 */
function PepGetToken() {
    global $GATEWAY;
    $data = array(
        'username' => $GATEWAY['Username'],
        'password' => $GATEWAY['Password'],
    );
    $curl = curl_init(PEP_BASE_URL . '/token/getToken');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Sign: ' . signData($data)
        )
    );
    $result = json_decode(curl_exec($curl));
    curl_close($curl);
    return $result;
}

/** Registers a purchase via the API. */
function PepPurchase(string $token, string $invoice, int $amount, string $callbackUrl) {
    global $GATEWAY;
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
    $curl = curl_init(PEP_BASE_URL . '/api/payment/purchase');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Sign: ' . signData($data),
            'Authorization: Bearer ' . $token,
        )
    );
    $result = json_decode(curl_exec($curl));
    curl_close($curl);
    return $result;
}

function error(?object $req, string $title): void {
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
		<p dir="ltr">' . ($req->resultMsg ?? 'خطای نامشخص') . '</p>
		<a href="' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceId . '">بازگشت >></a>
		<br><br>
	</main>
</body>
</html>';
    exit();
}
