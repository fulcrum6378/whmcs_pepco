<?php /** @noinspection PhpUnused */

function pasargad_config(): array {
    return array(
        "FriendlyName" => array(
            "Type" => "System",
            "Value" => "درگاه پرداخت پاسارگاد"
        ),
        "pasargad_terminal_id" => array(
            "FriendlyName" => "شماره ترمینال",
            "Type" => "text",
            "Size" => "50",
        ),
        "pasargad_merchant_id" => array(
            "FriendlyName" => "شماره فروشگاه",
            "Type" => "text",
            "Size" => "50",
        ),
        "Currencies" => array(
            "FriendlyName" => "واحد پول سیستم",
            "Type" => "dropdown",
            "Options" => "rial,toman",
            "Description" => "لطفا واحد پول سیستم خود را انتخاب کنید.",
        ),
    );
}

function pasargad_link($params): string {
    $ex_amount = explode('.', $params['amount']);
    $amount = $ex_amount[0];
    if ($params['Currencies'] == 'toman') $amount = $amount * 10;

    return '<form method="post" action="modules/gateways/pasargad/pay.php">
        <input type="hidden" name="invoice_id" value="' . $params['invoice_id'] . '" />
        <input type="hidden" name="amount" value="' . $amount . '" />
		<input type="hidden" name="email" value="' . $params['clientdetails']['email'] . '" />
        <input type="submit" name="pay" value=" پرداخت " />
    </form>
';
}
