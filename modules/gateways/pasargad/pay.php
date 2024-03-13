<?php /** @noinspection PhpUndefinedVariableInspection, PhpUndefinedFunctionInspection */

include "./includes/shared.php";

$terminalId = $GATEWAY['TerminalNumber'];
$amount = intval($_POST['amount']);
$invoiceId = $_POST['invoice_id'];
$email = $_POST['email'];

$orderId = $invoiceId . mt_rand(10, 100);
$callbackUrl = $whmcs_url . '/modules/gateways/pasargad/callback.php?amount=' . $amount . '&invoice_id=' . $invoiceId;

$request = PepPayRequest($orderId, $terminalId, $amount, $callbackUrl, '', $email);
echo implode(" - ", array_keys($request));
if (isset($request) && $request->IsSuccess)
    redirect('https://pep.shaparak.ir/payment.aspx?n=' . $request->Token);
else {
    $message = $request->Message ?? 'خطای نامشخص';
    echo '<!DOCTYPE html> 
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>خطا در ارسال به بانک</title>
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
		<span style="color: #FF0000;"><b>خطا در ارسال به بانک</b></span><br>
		<p style="text-align: center;">' . $message . '</p>
		<a href="' . $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceId . '">بازگشت >></a>
		<br><br>
	</main>
</body>
</html>';
}

function PepPayRequest($InvoiceNumber, $TerminalCode, $MerchantCode, $Amount, $RedirectAddress,
                       $Mobile = '', $Email = '') {
    require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
    $processor = new RSAProcessor(
        dirname(__FILE__) . '/includes/certificate.xml',
        RSAKeyType::XMLFile
    );
    if (!function_exists('jdate'))
        require_once(dirname(__FILE__) . '/includes/jdf.php');

    $data = array(
        /*'InvoiceNumber' => $InvoiceNumber,
        'InvoiceDate' => jdate('Y/m/d'),
        'TerminalCode' => $TerminalCode,
        'Amount' => $Amount,
        'RedirectAddress' => $RedirectAddress,
        'Timestamp' => date('Y/m/d H:i:s'),
        'Action' => 1003,
        'Mobile' => $Mobile,
        'Email' => $Email,*/
        'username' => 'test',
        'password' => '123456"',
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

function redirect($url) {
    if ($url == '') return;
    if (headers_sent())
        echo '<script type="text/javascript">window.location.assign("' . $url . '");</script>';
    else
        header("Location: $url");
    exit();
}
