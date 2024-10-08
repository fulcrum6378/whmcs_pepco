<?php /** @noinspection PhpUndefinedFunctionInspection, PhpUndefinedVariableInspection */

include "../pasargad/shared.php";

# 1. process the GET parameters.
$status = $_GET['status'] ?? '';
$extInvoiceId = $_GET['invoiceId'] ?? '';
$transactionId = $_GET['referenceNumber'] ?? ''; // 'null' on cancellation
$trackId = $_GET['trackId'] ?? '';

# 2. check if all the required GET parameters are passed.
if (empty($status) || empty($extInvoiceId) || ($status == 'success' && empty($transactionId)) || empty($trackId)) {
    echo errorPage('خطا در پارامتر های ورودی',
        '>' . 'پارامتر های { ' .
        (empty($status) ? 'status, ' : '') .
        (empty($extInvoiceId) ? 'invoiceId, ' : '') .
        (($status == 'success' && empty($transactionId)) ? 'referenceNumber, ' : '') .
        (empty($trackId) ? 'trackId, ' : '') .
        ' } وارد نشدند.');
    exit();
}

# 3. check if invoice ID is valid, or DIE!
$invoiceId = checkCbInvoiceID(
    intval(($_SESSION['pep_invoice_altered'] === true) ? substr($invoiceId, 0, -4) : $extInvoiceId),
    $GATEWAY['name']);

# 4. if transaction was unsuccessful, redirect to the page of the invoice.
if ($status != 'success') // 'cancel' | 'failed' | 'unknown'
    redirect(invoiceUrl($invoiceId));

# 5. check if transaction ID isn't duplicate, or DIE!
if ($transactionId != 'null') // normally it shouldn't be null when $status == 'success'
    checkCbTransID($transactionId); // void function

# 6. confirm the invoice.
$confirmation = API::confirmTransaction(
    $extInvoiceId,
    $_SESSION['pep_url_id'],
    $_SESSION['pep_token']
);
if (!isset($confirmation) || $confirmation->resultCode != 0) {
    logTransaction($GATEWAY["name"], array(
        'invoiceid' => $invoiceId,
        'order_id' => $invoiceId,
        'amount' => 0,
        'tran_id' => $transactionId,
        'track_id' => intval($trackId),
        'status' => 'unpaid'
    ), "ناموفق - پرداخت تایید نشد");

    echo errorPage(
        'پرداخت تایید نشد!',
        '>' . 'در صورتی که وجه پرداختی از حساب بانکی شما کسر شده باشد،
به صورت خودکار از سوی بانک به حساب شما باز خواهد گشت.<br>
(نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)',
        $invoiceId);
    exit();
}

# 7. log the transaction to the WHMCS Gateway Log.
logTransaction($GATEWAY["name"], array(
    'invoiceid' => $invoiceId,
    'order_id' => $invoiceId,
    'external_invoiceid' => $extInvoiceId,
    'amount' => $confirmation->data->amount,
    'tran_id' => $transactionId,
    'track_id' => intval($trackId),
    'status' => 'paid',
), "موفق");

# 8. apply the payment to the invoice.
addInvoicePayment($invoiceId, $transactionId, $confirmation->data->amount, 0, MODULE_NAME);

# 9. redirect to the page of the invoice.
redirect(invoiceUrl($invoiceId));

# for more information: https://developers.whmcs.com/payment-gateways/callbacks/
