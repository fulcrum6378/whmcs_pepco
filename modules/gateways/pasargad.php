<?php /** @noinspection PhpUnused */

function pasargad_config(): array {
    return array(
        "FriendlyName" => array(
            "Type" => "System",
            "Value" => "درگاه پرداخت پاسارگاد"
        ),
        "Username" => array(
            "FriendlyName" => "نام کاربری",
            "Type" => "text",
            "Size" => "50",
        ),
        "Password" => array(
            "FriendlyName" => "رمز عبور",
            "Type" => "text",
            "Size" => "50",
        ),
        "TerminalNumber" => array(
            "FriendlyName" => "شماره ترمینال",
            "Type" => "text",
            "Size" => "50",
        ),
        /*"MerchantID" => array(
            "FriendlyName" => "شماره فروشگاه",
            "Type" => "text",
            "Size" => "50",
        ),*/
        "Currency" => array(
            "FriendlyName" => "واحد پول سیستم",
            "Type" => "dropdown",
            "Options" => "rial,toman",
            "Description" => "لطفا واحد پول سیستم خود را انتخاب کنید.",
        ),
    );
}

function pasargad_link($params): string {
    $amount = explode('.', $params['amount'])[0];
    if ($params['Currency'] == 'toman') $amount = $amount * 10;

    return '<form method="post" action="modules/gateways/pasargad/pay.php">
        <input type="hidden" name="invoice_id" value="' . $params['invoice_id'] . '" />
        <input type="hidden" name="amount" value="' . $amount . '" />
		<input type="hidden" name="email" value="' . $params['clientdetails']['email'] . '" />
        <input type="submit" name="pay" value=" پرداخت " />
    </form>
';
}
