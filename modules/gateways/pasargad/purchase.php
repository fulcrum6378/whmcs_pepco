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
$tokenReq = API::getToken($GATEWAY['Username'], $GATEWAY['Password']);
if (isset($tokenReq) && $tokenReq->resultCode == 0)
    $_SESSION['pep_token'] = $tokenReq->token;
else
    error($tokenReq, 'خطا در دریافت توکن', $invoiceId);

# 3. send the purchase request and get a URL.
$purchase = API::purchase(
    $invoiceId,
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


/** Prints an HTML page for a specific error. */
function error(?object $req, string $title, ?int $invoiceId): void {
    echo errorPage($title, ' dir="ltr">' . ($req->resultMsg ?? 'خطای نامشخص'), $invoiceId);
    exit();
}
