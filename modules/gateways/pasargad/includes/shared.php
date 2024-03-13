<?php /** @noinspection PhpIncludeInspection, PhpUndefinedVariableInspection, PhpUndefinedFunctionInspection */

if (file_exists('../../../init.php'))
    require('../../../init.php');
else
    require("../../../dbconnect.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewayModule = 'pasargad';
$GATEWAY = getGatewayVariables($gatewayModule);
if (!$GATEWAY['type']) die('Module Not Activated'); # checks gateway module is active before accepting callback
$PEP_BASE_URL = 'https://pep.shaparak.ir/dorsa1';
$whmcs_url = $CONFIG['SystemURL'];
