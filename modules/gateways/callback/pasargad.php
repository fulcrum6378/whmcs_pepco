<?php

include "../pasargad/shared.php";

# 1. process the GET parameters.
$status = $_GET['status'] ?? '';
$extInvoiceId = $_GET['invoiceId'] ?? ''; // two latter digits are to be discarded
$transactionId = $_GET['referenceNumber'] ?? ''; // 'null' (on cancellation)
$trackId = $_GET['trackId'] ?? ''; // e.g. 18

# 2. check if all the required GET parameters are passed.
if (empty($status) || empty($extInvoiceId) || empty($transactionId) || empty($trackId)) {
    echo errorPage('خطا در پارامتر های ورودی',
        '>' . 'پارامتر های { ' .
        (empty($status) ? 'status, ' : '') .
        (empty($extInvoiceId) ? 'invoiceId, ' : '') .
        (empty($transactionId) ? 'referenceNumber, ' : '') .
        (empty($trackId) ? 'trackId, ' : '') .
        ' } وارد نشدند.');
    exit();
}

# 3. check if invoice ID is valid, or DIE!
$invoiceId = checkCbInvoiceID(intval(substr($extInvoiceId, 0, -2)), $GATEWAY['name']);

# 4. if transaction was unsuccessful, redirect to the page of the invoice.
if ($status != 'success') // 'cancel' | 'failed' | 'unknown'
    redirect(invoiceUrl($invoiceId));

# 5. check if transaction ID isn't duplicate, or DIE!
checkCbTransID($transactionId); // void

# 6. confirm the invoice.
$confirmation = PepConfirm($extInvoiceId);
echo json_encode($confirmation);
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
    'amount' => $confirmation->data->amount,
    'tran_id' => $transactionId,
    'track_id' => intval($trackId),
    'status' => 'paid'
), "موفق");

# 8. apply the payment to the invoice.
addInvoicePayment($invoiceId, $transactionId, $confirmation->data->amount, 0, MODULE_NAME);

# 9. redirect to the page of the invoice.
redirect(invoiceUrl($invoiceId));


function PepConfirm(string $invoiceId) {
    $data = array(
        'invoiceId' => $invoiceId,
        'urlId' => '',//TODO https://stackoverflow.com/questions/4662110/how-to-get-the-previous-url-using-php
    );
    $curl = curl_init(PEP_BASE_URL . '/api/payment/confirm-transactions');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $result = json_decode(curl_exec($curl));
    curl_close($curl);
    return $result;
}

# for more information: https://developers.whmcs.com/payment-gateways/callbacks/
