<?php

include "./includes/shared.php";

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
//TODO checkCbTransID($referenceNumber);
$paymentInquiry = PepPaymentInquiry($extInvoiceId);
/*if (!isset($paymentInquiry) || $paymentInquiry->resultCode != 0)
    redirect($invoiceUrl);*/
$tokenUrl = $paymentInquiry->data->url;
echo $tokenUrl . '\n';
echo $trackId . ' == ' . $paymentInquiry->data->trackId . '\n';
echo $referenceNumber . ' == ' . $paymentInquiry->data->referenceNumber . '\n';
echo json_encode($paymentInquiry);
exit();

$verification = PepVerifyRequest(
    $invoiceNumber, $invoiceDate, $GATEWAY['TerminalNumber'], $merchantId, $paymentInquiry->data->amount);

if (isset($verification) && $verification->IsSuccess) {
    addInvoicePayment($invoiceId, $transactionReferenceId, $paymentInquiry->data->amount, 0, MODULE_NAME);
    logTransaction($GATEWAY["name"], array(
        'invoiceid' => $invoiceId,
        'order_id' => $invoiceId,
        'amount' => $paymentInquiry->data->amount,
        'tran_id' => $transactionReferenceId,
        'refcode' => $transactionReferenceId,
        'status' => 'paid'
    ), "موفق");
    Header('Location: ' . $WHMCS_URL . '/viewinvoice.php?id=' . $invoiceId);
} else $message = $verification->Message;
display_error($error_code ?? null, $transactionReferenceId, $orderId, $getAmount, $message ?? '');

function PepPaymentInquiry(string $invoiceId) {
    global $GATEWAY;
    $data = array(
        'invoiceId' => $invoiceId,
        'terminalNumber' => $GATEWAY['TerminalNumber'],
    );
    $curl = curl_init(PEP_BASE_URL . '/api/v2/payment/payment-inquiry');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $result = json_decode(curl_exec($curl));
    curl_close($curl);
    return $result;
}

function PepVerifyRequest($InvoiceNumber, $InvoiceDate, $TerminalCode, $MerchantCode, $Amount) {
    $data = array(
        'InvoiceNumber' => $InvoiceNumber,
        'InvoiceDate' => $InvoiceDate,
        'TerminalCode' => $TerminalCode,
        'MerchantCode' => $MerchantCode,
        'Amount' => $Amount,
        'Timestamp' => date('Y/m/d H:i:s')
    );
    $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/VerifyPayment');
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

function PepReversalRequest($InvoiceNumber, $InvoiceDate, $TerminalCode, $MerchantCode) {
    $data = array(
        'InvoiceNumber' => $InvoiceNumber,
        'InvoiceDate' => $InvoiceDate,
        'TerminalCode' => $TerminalCode,
        'MerchantCode' => $MerchantCode,
        'Timestamp' => date('Y/m/d H:i:s')
    );
    $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/RefundPayment');
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

function display_error($pay_status = '', $tran_id = '', $order_id = '', $amount = '', $message = '') {
    if ($pay_status == 'retry') {
        $title = 'خطای موقت در پرداخت';
        $adminMsg = 'در هنگام بازگشت خریدار از بانک سرور بانک پاسخ نداد ، از خریدار درخواست شد صفحه را رفرش کند';
        $retry_mess = '
			<div style="margin:15px 0 21px 0; font-size: 12px;">
				سرور درگاه اینترنتی <span style="color:#ff0000;">به صورت موقت</span> با مشکل مواجه شده است،
				جهت تکمیل تراکنش لحظاتی بعد بر روی دکمه زیر کلیک کنید
			</div>
			<div style="margin:20px 0 25px 0; color:#008800;" id="reqreload">
				<button onclick="reload_page()">تلاش مجدد</button>
			</div>
			<script>
				function reload_page(){
					document.getElementById("reqreload").innerHTML = "در حال تلاش مجدد لطفا صبر کنید ..";
					location.reload();
				}
			</script>';
    } elseif ($pay_status == 'reversal_done') {
        $title = 'مشکل در ارائه خدمات';
        $adminMsg = 'خریدار مبلغ را پرداخت کرد اما در هنگام بازگشت از بانک مشکلی در ارائه خدمات رخ داد،
دستور بازگشت وجه به حساب خریدار در بانک ثبت شد';
        $clientMsg = 'پرداخت شما با شماره پیگیری ' . $tran_id .
            ' با موفقیت در بانک انجام شده است اما در ارائه خدمات مشکلی رخ داده است !<br>
دستور بازگشت وجه به حساب شما در بانک ثبت شده است،
در صورتی که وجه پرداختی تا ساعات آینده به حساب شما بازگشت داده نشد با پشتیبانی تماس بگیرید
(نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)';
    } elseif ($pay_status == 'reversal_error') {
        $title = 'مشکل در ارائه خدمات';
        $adminMsg = 'خریدار مبلغ را پرداخت کرد اما در هنگام بازگشت از بانک مشکلی در ارائه خدمات رخ داد ،
دستور بازگشت وجه به حساب خریدار در بانک ثبت شد اما متاسفانه با خطا روبرو شد،
به این خریدار باید یا خدمات ارائه شود یا وجه استرداد گردد';
        $clientMsg = 'پرداخت شما با شماره پیگیری ' . $tran_id .
            ' با موفقیت در بانک انجام شده است اما در ارائه خدمات مشکلی رخ داده است!<br>
به منظور ثبت دستور بازگشت وجه به حساب شما در بانک اقدام شد اما متاسفانه با خطا روبرو شد،
لطفا به منظور دریافت خدمات و یا استرداد وجه پرداختی با پشتیبانی تماس بگیرید';
    } else {
        $title = $adminMsg = 'پرداخت انجام نشد';
        $clientMsg = $message;
    }
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
