<?php /** @noinspection PhpIncludeInspection, PhpUndefinedVariableInspection, PhpUndefinedFunctionInspection */

if (file_exists('../../../init.php'))
    require '../../../init.php';
else
    require "../../../dbconnect.php";
include "../../../includes/functions.php";
include "../../../includes/gatewayfunctions.php";
include "../../../includes/invoicefunctions.php";

$MODULE_NAME = 'pasargad';
$GATEWAY = getGatewayVariables($MODULE_NAME);
if (!$GATEWAY['type']) die('Module Not Activated'); # checks gateway module is active before accepting callback.
$PEP_BASE_URL = 'https://pep.shaparak.ir/dorsa1';
$WHMCS_URL = $CONFIG['SystemURL'];
