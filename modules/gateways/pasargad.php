<?php /** @noinspection PhpUnused */

if (!defined('WHMCS')) die('This file cannot be accessed directly');

function pasargad_config(): array {
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'درگاه پرداخت پاسارگاد'
        ),
        'TerminalNumber' => array(
            'FriendlyName' => 'شماره ترمینال پرداختی',
            'Type' => 'text',
            'Size' => '50',
        ),
        'Username' => array(
            'FriendlyName' => 'نام کاربری',
            'Type' => 'text',
            'Size' => '50',
        ),
        'Password' => array(
            'FriendlyName' => 'رمز عبور',
            'Type' => 'text',//password
            'Size' => '50',
        ),
        'IrrCurrency' => array(
            'FriendlyName' => 'واحد پول سیستم',
            'Type' => 'dropdown',
            'Options' => array('rial' => 'ریال', 'toman' => 'تومان'),
            'Default' => 'rial',
            'Description' => 'لطفا واحد پول سیستم خود را انتخاب کنید.',
        ),
    );
}

function pasargad_link($params): string {
    $amount = explode('.', $params['amount'])[0];
    if ($params['IrrCurrency'] == 'toman') $amount = $amount * 10;

    return '<form method="post" action="modules/gateways/pasargad/pay.php">
        <input type="hidden" name="invoice_id" value="' . $params['invoiceid'] . '" />
        <input type="hidden" name="amount" value="' . $amount . '" />
        <input type="submit" name="pay" value=" پرداخت " />
    </form>
';
}

# for more information: https://developers.whmcs.com/payment-gateways/third-party-gateway/
