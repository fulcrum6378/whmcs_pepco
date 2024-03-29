<?php /** @noinspection PhpIncludeInspection, PhpUndefinedFunctionInspection */

# global constants
const MODULE_NAME = 'pasargad';
const PEP_BASE_URL = 'https://pep.shaparak.ir/dorsa1';

# shared inclusions
if (file_exists('../../../init.php'))
    require '../../../init.php';
else
    require '../../../dbconnect.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';
require 'API.php';

# shared variables
$GATEWAY = getGatewayVariables(MODULE_NAME);
if (!$GATEWAY['type']) die('Module Not Activated'); # checks gateway module is active before accepting callback.


/** Signs data for a secure API call using the RSAProcessor class and returns the signing key. */
function signData(array $data): string {
    require_once(dirname(__FILE__) . '/RSA.php');
    $processor = new RSAProcessor(
        dirname(__FILE__) . '/certificate.xml',
        RSAKeyType::XMLFile
    );
    $sign_data = json_encode($data);
    $sign_data = sha1($sign_data, true);
    $sign_data = $processor->sign($sign_data);
    return base64_encode($sign_data);
}

/** Helper function for properly redirecting the user. */
function redirect(string $url): void {
    if ($url == '') return;
    if (headers_sent())
        echo '<script type="text/javascript">window.location.assign("' . $url . '");</script>';
    else
        header("Location: $url");
    exit();
}

function invoiceUrl(int $invoiceId): string {
    global $CONFIG;
    return $CONFIG['SystemURL'] . '/viewinvoice.php?id=' . $invoiceId;
}

/** Returns an HTML template page displaying the error messages. */
function errorPage(string $title, string $paragraph, ?int $invoiceId = null): string {
    return '<!DOCTYPE html> 
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
    padding: 12px;
}
</style>
</head>

<body>
	<main>
		<span style="color: #FF0000;"><b>' . $title . '</b></span><br>
		<p' . $paragraph . '</p>
		' . (($invoiceId != null) ? '<a href="' . invoiceUrl($invoiceId) . '">بازگشت >></a>' : '') . '
	</main>
</body>
</html>';
}
