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

$TerminalID = $GATEWAY['pasargad_terminal_id'];
$MerchantID = $GATEWAY['pasargad_merchant_id'];
$amount = intval($_POST['amount']);
$invoiceId = $_POST['invoiceid'];
$email = $_POST['email'];

$order_id = $invoiceId . mt_rand(10, 100);
$CallBackUrl = $CONFIG['SystemURL'] . '/modules/gateways/pasargad/callback.php?a=' . $amount .
    '&invoiceid=' . $invoiceId;

$Request = PepPayRequest($order_id, $TerminalID, $MerchantID, $amount, $CallBackUrl, '', $email);
if (isset($Request) && $Request->IsSuccess) {
    redirect('https://pep.shaparak.ir/payment.aspx?n=' . $Request->Token);
} else {
    $message = $Request->Message ?? 'خطای نامشخص';
    echo '
	<!DOCTYPE html> 
	<html xmlns="http://www.w3.org/1999/xhtml" lang="fa">
	<head>
	<title>خطا در ارسال به بانک</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<style>body{font-family:tahoma;text-align:center;margin-top:30px;}</style>
	</head>
	<body>
		<div align="center" dir="rtl" style="font-family:tahoma;font-size:12px;border:1px dotted #c3c3c3; width:60%; margin: 50px auto 0px auto;line-height: 25px;padding-left: 12px;padding-top: 8px;">
			<span style="color:#ff0000;"><b>خطا در ارسال به بانک</b></span><br/>
			<p style="text-align:center;">' . $message . '</p>
			<a href="' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceid . '">بازگشت >></a><br/><br/>
		</div>
	</body>
	</html>
	';
}
function PepPayRequest($InvoiceNumber, $TerminalCode, $MerchantCode, $Amount, $RedirectAddress,
                       $Mobile = '', $Email = '') {
    require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
    $processor = new RSAProcessor(dirname(__FILE__) . '/includes/certificate.xml',
        RSAKeyType::XMLFile);
    if (!function_exists('jdate'))
        require_once(dirname(__FILE__) . '/includes/jdf.php');
    $data = array(
        'InvoiceNumber' => $InvoiceNumber,
        'InvoiceDate' => jdate('Y/m/d'),
        'TerminalCode' => $TerminalCode,
        'MerchantCode' => $MerchantCode,
        'Amount' => $Amount,
        'RedirectAddress' => $RedirectAddress,
        'Timestamp' => date('Y/m/d H:i:s'),
        'Action' => 1003,
        'Mobile' => $Mobile,
        'Email' => $Email
    );

    $sign_data = json_encode($data);
    $sign_data = sha1($sign_data, true);
    $sign_data = $processor->sign($sign_data);
    $sign = base64_encode($sign_data);

    $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/GetToken');
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

function redirect($url) {
    if ($url != '') {
        if (headers_sent()) {
            echo '<script type="text/javascript">window.location.assign("' . $url . '")</script>';
        } else {
            header("Location: $url");
        }
        exit();
    }
}
