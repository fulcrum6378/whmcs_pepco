<?php /** @noinspection PhpIncludeInspection, PhpUndefinedVariableInspection, PhpUndefinedFunctionInspection */

# global constants
const MODULE_NAME = 'pasargad';
const PEP_BASE_URL = 'https://pep.shaparak.ir/dorsa1';

# shared inclusions
if (file_exists('../../../init.php'))
    require '../../../init.php';
else
    require "../../../dbconnect.php";
include "../../../includes/functions.php";
include "../../../includes/gatewayfunctions.php";
include "../../../includes/invoicefunctions.php";

# shared variables
$GATEWAY = getGatewayVariables(MODULE_NAME);
if (!$GATEWAY['type']) die('Module Not Activated'); # checks gateway module is active before accepting callback.
$WHMCS_URL = $CONFIG['SystemURL'];

/** Signs data for a secure API call using the RSAProcessor class and returns the signing key. */
function signData(array $data): string {
    require_once(dirname(__FILE__) . '/RSAProcessor.class.php');
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
