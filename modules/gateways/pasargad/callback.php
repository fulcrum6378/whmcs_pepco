<?php
if (file_exists('../../../init.php')) {
    require('../../../init.php');
} else {
    require("../../../dbconnect.php");
}
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewaymodule = 'pasargad';
$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY['type']) die('Module Not Activated'); # Checks gateway module is active before accepting callback
$whmcs_url = $CONFIG['SystemURL'];

$order_id = isset($_GET['invoiceid']) ? $_GET['invoiceid'] : '';
$get_amount = isset($_GET['a']) ? $_GET['a'] : '';
$TransactionReferenceID = isset($_REQUEST['tref']) ? $_REQUEST['tref'] : '';
$InvoiceNumber = isset($_REQUEST['iN']) ? $_REQUEST['iN'] : '';
$InvoiceDate = isset($_REQUEST['iD']) ? $_REQUEST['iD'] : '';
$TerminalID = $GATEWAY['pasargad_terminal_id'];
$MerchantID = $GATEWAY['pasargad_merchant_id'];


if ($order_id == substr($InvoiceNumber, 0, -2)) {
    $invoiceid = checkCbInvoiceID($order_id, $GATEWAY['name']);
    if (isset($invoiceid) && !empty($invoiceid)) {
        checkCbTransID($TransactionReferenceID);

        if ($TransactionReferenceID != '') {
            $checkResult = PepCheckTransactionResult($TransactionReferenceID);
        } else {
            $checkResult = PepCheckTransactionResult(null, $InvoiceNumber, $InvoiceDate, $TerminalID, $MerchantID);
        }

        if (isset($checkResult) && $checkResult->IsSuccess && $checkResult->InvoiceNumber == $InvoiceNumber) {
            $amount = $checkResult->Amount;

            if (strlen($get_amount) == 0 || $get_amount != $amount) {
                $message = 'مبلغ پرداختی نادرست است ، وجه کسر شده به صورت خودکار از سوی بانک به حساب شما بازگشت داده خواهد شد.';
            } else {
                $Request = PepVerifyRequest($InvoiceNumber, $InvoiceDate, $TerminalID, $MerchantID, $amount);
                if (isset($Request) && $Request->IsSuccess) {
                    addInvoicePayment($invoiceid, $TransactionReferenceID, $amount, 0, $gatewaymodule);
                    logTransaction($GATEWAY["name"], array('invoiceid' => $invoiceid, 'order_id' => $invoiceid, 'amount' => $amount, 'tran_id' => $TransactionReferenceID, 'refcode' => $TransactionReferenceID, 'status' => 'paid'), "موفق");
                    Header('Location: ' . $whmcs_url . '/viewinvoice.php?id=' . $invoiceid);
                } else {
                    $message = $Request->Message;
                }
            }
        } else {
            $message = 'پرداخت توسط شما انجام نشده است';
        }
    } else {
        $error_code = 'order_not_exist';
    }
} else {
    $error_code = 'order_not_for_this_person';
}
display_error(isset($error_code) ? $error_code : null, $TransactionReferenceID, $order_id, $get_amount, isset($message) ? $message : '');

function PepCheckTransactionResult($TransactionReferenceID, $InvoiceNumber = '', $InvoiceDate = '', $TerminalCode = '', $MerchantCode = '')
{
    $data = array(
        'InvoiceNumber' => $InvoiceNumber,
        'InvoiceDate' => $InvoiceDate,
        'TerminalCode' => $TerminalCode,
        'MerchantCode' => $MerchantCode,
        'TransactionReferenceID' => $TransactionReferenceID
    );
    $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/CheckTransactionResult');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        )
    );
    $result = json_decode(curl_exec($curl));
    curl_close($curl);

    return $result;
}

function PepVerifyRequest($InvoiceNumber, $InvoiceDate, $TerminalCode, $MerchantCode, $Amount)
{
    require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
    $processor = new RSAProcessor(dirname(__FILE__) . '/includes/certificate.xml', RSAKeyType::XMLFile);
    $data = array(
        'InvoiceNumber' => $InvoiceNumber,
        'InvoiceDate' => $InvoiceDate,
        'TerminalCode' => $TerminalCode,
        'MerchantCode' => $MerchantCode,
        'Amount' => $Amount,
        'Timestamp' => date('Y/m/d H:i:s')
    );

    $sign_data = json_encode($data);
    $sign_data = sha1($sign_data, true);
    $sign_data = $processor->sign($sign_data);
    $sign = base64_encode($sign_data);

    $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/VerifyPayment');
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

function PepReversalRequest($InvoiceNumber, $InvoiceDate, $TerminalCode, $MerchantCode)
{
    require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
    $processor = new RSAProcessor(dirname(__FILE__) . '/includes/certificate.xml', RSAKeyType::XMLFile);
    $data = array(
        'InvoiceNumber' => $InvoiceNumber,
        'InvoiceDate' => $InvoiceDate,
        'TerminalCode' => $TerminalCode,
        'MerchantCode' => $MerchantCode,
        'Timestamp' => date('Y/m/d H:i:s')
    );

    $sign_data = json_encode($data);
    $sign_data = sha1($sign_data, true);
    $sign_data = $processor->sign($sign_data);
    $sign = base64_encode($sign_data);

    $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/RefundPayment');
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

function display_error($pay_status = '', $tran_id = '', $order_id = '', $amount = '', $message = '')
{
    global $GATEWAY, $CONFIG;
    if ($pay_status == 'retry') {
        $page_title = 'خطای موقت در پرداخت';
        $admin_mess = 'در هنگام بازگشت خریدار از بانک سرور بانک پاسخ نداد ، از خریدار درخواست شد صفحه را رفرش کند';
        $retry_mess = '
			<div style="margin:15px 0px 21px 0px;font-size: 12px;">
				سرور درگاه اینترنتی <span style="color:#ff0000;">به صورت موقت</span> با مشکل مواجه شده است ، جهت تکمیل تراکنش لحظاتی بعد بر روی دکمه زیر کلیک کنید
			</div>
			<div style="margin:20px 0px 25px 0px;color:#008800;" id="reqreload">
				<button onclick="reload_page()">تلاش مجدد</button>
			</div>
			<script>
				function reload_page(){
					document.getElementById("reqreload").innerHTML = "در حال تلاش مجدد لطفا صبر کنید ..";
					location.reload();
				}
			</script>';
    } elseif ($pay_status == 'reversal_done') {
        $page_title = 'مشکل در ارائه خدمات';
        $admin_mess = 'خریدار مبلغ را پرداخت کرد اما در هنگام بازگشت از بانک مشکلی در ارائه خدمات رخ داد ، دستور بازگشت وجه به حساب خریدار در بانک ثبت شد';
        $client_mess = 'پرداخت شما با شماره پیگیری ' . $tran_id . ' با موفقیت در بانک انجام شده است اما در ارائه خدمات مشکلی رخ داده است !<br />دستور بازگشت وجه به حساب شما در بانک ثبت شده است ، در صورتی که وجه پرداختی تا ساعات آینده به حساب شما بازگشت داده نشد با پشتیبانی تماس بگیرید (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)';
    } elseif ($pay_status == 'reversal_error') {
        $page_title = 'مشکل در ارائه خدمات';
        $admin_mess = 'خریدار مبلغ را پرداخت کرد اما در هنگام بازگشت از بانک مشکلی در ارائه خدمات رخ داد ، دستور بازگشت وجه به حساب خریدار در بانک ثبت شد اما متاسفانه با خطا روبرو شد ، به این خریدار باید یا خدمات ارائه شود یا وجه استرداد گردد';
        $client_mess = 'پرداخت شما با شماره پیگیری ' . $tran_id . ' با موفقیت در بانک انجام شده است اما در ارائه خدمات مشکلی رخ داده است !<br />به منظور ثبت دستور بازگشت وجه به حساب شما در بانک اقدام شد اما متاسفانه با خطا روبرو شد ، لطفا به منظور دریافت خدمات و یا استرداد وجه پرداختی با پشتیبانی تماس بگیرید';
    } elseif ($pay_status == 'order_not_exist') {
        $page_title = 'سفارش یافت نشد';
        $admin_mess = 'سفارش در سایت یافت نشد';
        $client_mess = 'متاسفانه سفارش شما در سایت یافت نشد ! در صورتی که وجه پرداختی از حساب بانکی شما کسر شده باشد به صورت خودکار از سوی بانک به حساب شما باز خواهد گشت (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)';
    } elseif ($pay_status == 'order_not_for_this_person') {
        $page_title = $admin_mess = 'شماره سفارش نادرست است';
        $client_mess = 'شماره سفارش نادرست است ؛ در صورت نیاز به پشتیبانی تماس بگیرید';
    } elseif ($pay_status == 'invoice_id_is_blank') {
        $page_title = 'خطا در پارامتر ورودی';
        $admin_mess = 'پس از بازگشت از بانک شماره سفارش موجود نبود';
        $client_mess = 'متاسفانه پارامتر ورودی شما معتبر نیست ! در صورتی که وجه پرداختی از حساب بانکی شما کسر شده باشد به صورت خودکار از سوی بانک به حساب شما باز خواهد گشت (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)';
    } else {
        $page_title = $admin_mess = 'پرداخت انجام نشد';
        $client_mess = $message;
    }
    echo '
	<!DOCTYPE html> 
	<html xmlns="http://www.w3.org/1999/xhtml" lang="fa">
	<head>
	<title>' . $page_title . '</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<style>body{font-family:tahoma;text-align:center;margin-top:30px;}</style>
	</head>
	<body>
		<div align="center" dir="rtl" style="font-family:tahoma;font-size:12px;border:1px dotted #c3c3c3; width:60%; margin: 50px auto 0px auto;line-height: 25px;padding-left: 12px;padding-top: 8px;">
			<span style="color:#ff0000;"><b>' . $page_title . '</b></span><br/>';
    if (isset($retry_mess)) {
        echo $retry_mess;
    } else {
        echo '<p style="text-align:right;margin-right:8px;">' . $client_mess . '</p><a href="' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $order_id . '">بازگشت >></a><br/><br/>';
    }
    echo '</div>
	</body>
	</html>
	';

    if (isset($admin_mess)) {
        logTransaction($GATEWAY["name"], array('invoiceid' => $order_id, 'order_id' => $order_id, 'amount' => $amount, 'tran_id' => $tran_id, 'status' => 'unpaid'), "ناموفق - $admin_mess");
    }
    exit();
}
