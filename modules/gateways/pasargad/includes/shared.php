<?php /** @noinspection PhpIncludeInspection, PhpUndefinedVariableInspection, PhpUndefinedFunctionInspection */

$MODULE_NAME = 'pasargad';
$PEP_BASE_URL = 'https://pep.shaparak.ir/dorsa1';

if (file_exists('../../../init.php'))
    require '../../../init.php';
else
    require "../../../dbconnect.php";
include "../../../includes/functions.php";
include "../../../includes/gatewayfunctions.php";
include "../../../includes/invoicefunctions.php";

$GATEWAY = getGatewayVariables($MODULE_NAME);
if (!$GATEWAY['type']) die('Module Not Activated'); # checks gateway module is active before accepting callback.
$WHMCS_URL = $CONFIG['SystemURL'];
