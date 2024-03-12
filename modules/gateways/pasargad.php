<?php
function pasargad_config()
{
    $configarray = array(
        "FriendlyName" => array("Type" => "System", "Value" => "درگاه پرداخت پاسارگاد"),
        "pasargad_terminal_id" => array("FriendlyName" => "شماره ترمینال", "Type" => "text", "Size" => "50",),
        "pasargad_merchant_id" => array("FriendlyName" => "شماره فروشگاه", "Type" => "text", "Size" => "50",),
        "Currencies" => array("FriendlyName" => "واحد پول سیستم", "Type" => "dropdown", "Options" => "rial,toman", "Description" => "لطفا واحد پول سیستم خود را انتخاب کنید.",),
    );
    return $configarray;
}

function pasargad_link($params)
{
    $currencies = $params['Currencies'];
    $invoiceid = $params['invoiceid'];
    $ex_amount = explode('.', $params['amount']);
    $amount = $ex_amount[0];
    $email = $params['clientdetails']['email'];

    if ($currencies == 'toman') {
        $amount = $amount * 10;
    }
    $code = '<form method="post" action="modules/gateways/pasargad/pay.php">
        <input type="hidden" name="invoiceid" value="' . $invoiceid . '" />
        <input type="hidden" name="amount" value="' . $amount . '" />
		<input type="hidden" name="email" value="' . $email . '" />
        <input type="submit" name="pay" value=" پرداخت " /></form>
    ';
    return $code;
}
