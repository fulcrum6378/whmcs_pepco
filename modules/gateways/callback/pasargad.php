<?php

include "../pasargad/includes/shared.php";

# process the GET parameters
$status = $_GET['status'] ?? '';
$extInvoiceId = $_GET['invoiceId'] ?? ''; // two latter digits are to be discarded
$referenceNumber = $_GET['referenceNumber'] ?? ''; // 'null' (on cancellation)
$trackId = $_GET['trackId'] ?? ''; // 18

# check if all the required GET parameters are passed
if (empty($status) || empty($extInvoiceId) || empty($referenceNumber) || empty($trackId))
    error('خطا در پارامتر های ورودی',
        'متاسفانه پارامتر ورودی شما معتبر نیست!
در صورتی که وجه پرداختی از حساب بانکی شما کسر شده باشد
به صورت خودکار از سوی بانک به حساب شما باز خواهد گشت (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)',
        'پس از بازگشت از بانک شماره سفارش موجود نبود');

# check if the requested invoice exists
$invoiceId = substr($extInvoiceId, 0, -2);
if (empty(checkCbInvoiceID($invoiceId, $GATEWAY['name'])))
    error('سفارش یافت نشد',
        'متاسفانه سفارش شما در سایت یافت نشد !
در صورتی که وجه پرداختی از حساب بانکی شما کسر شده باشد
به صورت خودکار از سوی بانک به حساب شما باز خواهد گشت (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)',
        'سفارش در سایت یافت نشد');

// redirect to page of invoice, if unsuccessful...
$invoiceUrl = $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceId;
if ($status != 'success') // 'cancel' | 'failed' | 'unknown'
    redirect($invoiceUrl);

// confirm the invoice
$confirmation = PepConfirm($extInvoiceId);
checkCbTransID($referenceNumber);
/*if (!isset($confirmation) || $confirmation->resultCode != 0)
    error();*/
echo json_encode($confirmation);
exit();

addInvoicePayment($invoiceId, $transactionReferenceId, $confirmation->data->amount, 0, MODULE_NAME);
logTransaction($GATEWAY["name"], array(
    'invoiceid' => $invoiceId,
    'order_id' => $invoiceId,
    'amount' => $confirmation->data->amount,
    'tran_id' => $transactionReferenceId,
    'refcode' => $transactionReferenceId,
    'status' => 'paid'
), "موفق");
Header('Location: ' . $WHMCS_URL . '/viewinvoice.php?id=' . $invoiceId);

function PepConfirm(string $invoiceId) {
    $data = array(
        'invoiceId' => $invoiceId,
        'urlId' => '',//TODO
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

function error($title, $clientMsg, $adminMsg): void {
    global $CONFIG, $GATEWAY, $invoiceId;
    echo '<!DOCTYPE html> 
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>' . $title . '</title>
<style>
body {
    font-family: tahoma;
    text-align: center;
    margin-top: 30px;
}
main {
    font-family: tahoma;
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
		<span style="color: #FF0000;"><b>' . $title . '</b></span><br>';
    if (isset($retry_mess)) {
        echo $retry_mess;
    } else {
        echo '
        <p style="text-align: right; margin-right:8px;">' . $clientMsg . '</p>
        <a href="' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceId . '">بازگشت >></a>
        <br><br>';
    }
    echo '
    </main>
</body>
</html>';

    logTransaction($GATEWAY["name"], array(
        'invoiceid' => $invoiceId,
        'order_id' => $invoiceId,
        'amount' => $amount,
        'tran_id' => $tran_id,
        'status' => 'unpaid'
    ), "ناموفق - $adminMsg");
    exit();
}

# for more information: https://developers.whmcs.com/payment-gateways/callbacks/
