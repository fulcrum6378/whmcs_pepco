<?php /** @noinspection PhpUndefinedVariableInspection */

include "shared.php";

# 1. process the POST parameters.
if (empty($_POST['invoice_id']) || empty($_POST['amount']) ||
    empty(intval($_POST['invoice_id'])) || empty(intval($_POST['amount']))
) error((object)['resultMsg' =>
    'پارامتر های { ' .
    (empty($_POST['invoice_id']) ? 'invoice_id, ' : '') .
    (empty($_POST['amount']) ? 'amount, ' : '') .
    ' } وارد نشدند.'
], 'خطا در پارامتر های ورودی', $invoiceId);
$invoiceId = intval($_POST['invoice_id']);

# 2. get a token for interacting with API.
$tokenReq = PepGetToken($GATEWAY['Username'], $GATEWAY['Password']);
if (isset($tokenReq) && $tokenReq->resultCode == 0)
    $_SESSION['pep_token'] = $tokenReq->token;
else
    error($tokenReq, 'خطا در دریافت توکن', $invoiceId);

# 3. send the purchase request and get a URL.
$purchase = PepPurchase(
    $invoiceId . mt_rand(10, 100),//FIXME
    intval($_POST['amount']),
    $CONFIG['SystemURL'] . '/modules/gateways/callback/pasargad.php',
    $GATEWAY['TerminalNumber'],
    $_SESSION['pep_token']
);

# 4. redirect to the received token-like URL (different from the API token).
if (isset($purchase) && $purchase->resultCode == 0) {
    $_SESSION['pep_url_id'] = $purchase->data->urlId;
    redirect(PEP_BASE_URL . '/' . $purchase->data->urlId);
} else
    error($purchase, 'خطا در ارسال به بانک', $invoiceId);


/**
 * Retrieves a token for future interactions with the API.
 *
 * Test via PowerShell:
 * $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
 * Invoke-WebRequest -UseBasicParsing -Uri "https://pep.shaparak.ir/dorsa1/token/getToken" `
 * -Method "POST" `
 * -WebSession $session `
 * -ContentType "application/json" `
 * -Body '{"username": "<USERNAME>", "password": "<PASSWORD>"}'
 */
function PepGetToken(string $username, string $password) {
    $data = array(
        'username' => $username,
        'password' => $password
    );
    $curl = curl_init(PEP_BASE_URL . '/token/getToken');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Sign: ' . signData($data),
        )
    );
    $result = json_decode(curl_exec($curl));
    curl_close($curl);
    return $result;
}

/**
 * Registers a purchase via the API.
 * This work should not be done in the _link() function;
 * because the POST request needs the token to be put in the headers not the body!
 * @see https://stackoverflow.com/questions/9516865/how-to-set-a-header-field-on-post-a-form/9516955#9516955
 */
function PepPurchase(string $invoice, int $amount, string $callbackUrl, string $terminalNumber, string $token) {
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
        'terminalNumber' => $terminalNumber,
        'nationalCode' => '',
        'pans' => ''
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

/** Prints an HTML page for a specific error. */
function error(?object $req, string $title, ?int $invoiceId): void {
    echo errorPage($title, ' dir="ltr">' . ($req->resultMsg ?? 'خطای نامشخص'), $invoiceId);
    exit();
}
