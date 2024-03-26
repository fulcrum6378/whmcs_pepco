<?php /** @noinspection PhpUnused */

class API {
    private static function post(
        string  $endPoint,
        array   $data,
        ?string $token = null
    ) {
        $curl = curl_init(PEP_BASE_URL . $endPoint);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            'Content-Type: application/json',
            'Sign: ' . signData($data),
        );
        if (!is_null($token)) $headers[] = 'Authorization: Bearer ' . $token;
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $result = json_decode(curl_exec($curl));
        curl_close($curl);
        return $result;
    }

    /**
     * Retrieves a token for future interactions with the API.
     *
     * Test via PowerShell:
     * $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
     * Invoke-WebRequest -UseBasicParsing -Uri "https://pep.shaparak.ir/dorsa1/token/getToken" `
     * -Method "POST" `
     * -WebSession $session `
     * -ContentType "application/json" `
     * -Body '{"username": "<USERNAME>", "password": "<PASSWORD>"}'
     */
    static function getToken(string $username, string $password) {
        return self::post('/token/getToken', array(
            'username' => $username,
            'password' => $password
        ));
    }

    /**
     * Registers a purchase via the API.
     * This work should not be done in the _link() function;
     * because the POST request needs the token to be put in the headers not the body!
     * @see https://stackoverflow.com/questions/9516865/how-to-set-a-header-field-on-post-a-form/9516955#9516955
     */
    static function purchase(
        string $invoice,
        int    $amount,
        string $callbackUrl,
        string $terminalNumber,
        string $token
    ) {
        return self::post('/api/payment/purchase', array(
            'amount' => $amount,
            'callbackApi' => $callbackUrl,
            'description' => '',
            'invoice' => $invoice,
            'invoiceDate' => date('Y-m-d'),
            'mobileNumber' => '',
            'payerMail' => '',
            'payerName' => '',
            'serviceCode' => 8,
            'serviceType' => 'PURCHASE',
            'terminalNumber' => $terminalNumber,
            'nationalCode' => '',
            'pans' => ''
        ), $token);
    }

    /** Experimental; not tested yet. */
    static function purchaseWithPaymentCode(
        string $invoice,
        int    $amount,
        string $callbackUrl,
        string $terminalNumber,
        string $paymentCode,
        string $token
    ) {
        return self::post('/api/payment/purchase', array(
            'amount' => $amount,
            'callbackApi' => $callbackUrl,
            'description' => '',
            'invoice' => $invoice,
            'invoiceDate' => date('Y-m-d'),
            'mobileNumber' => '',
            'payerMail' => '',
            'payerName' => '',
            'paymentCode' => $paymentCode,
            'serviceCode' => 8,
            'serviceType' => 'PURCHASE',
            'terminalNumber' => $terminalNumber,
            'pans' => ''
        ), $token);
    }

    /** Experimental; not tested yet. */
    static function purchaseMultiAcc(
        string $invoice,
        int    $amount,
        string $callbackUrl,
        array  $sharedValue,
        array  $sheba,
        string $terminalNumber,
        string $token
    ) {
        return self::post('/api/payment/multiacc/purchase', array(
            'amount' => $amount,
            'callbackApi' => $callbackUrl,
            'description' => '',
            'invoice' => $invoice,
            'invoiceDate' => date('Y-m-d'),
            'mobileNumber' => '',
            'payerMail' => '',
            'payerName' => '',
            'serviceCode' => 9,
            'serviceType' => 'MULTIACCPURCHASE',
            'sharedValue' => $sharedValue,
            'sheba' => $sheba,
            'terminalNumber' => $terminalNumber,
            'pans' => ''
        ), $token);
    }

    /** Experimental; not tested yet. */
    static function preTransaction(
        string $invoice,
        int    $amount,
        string $callbackUrl,
        string $productCode,
        string $terminalNumber,
        string $serviceCode,
        string $serviceType,
        string $billId,
        string $paymentId,
        string $token
    ) {
        return self::post('/api/payment/pre-transaction', array(
            'amount' => $amount,
            'callbackApi' => $callbackUrl,
            'description' => '',
            'invoice' => $invoice,
            'payerMail' => '',
            'payerName' => '',
            'productCode' => $productCode,
            'mobileNumber' => '',
            'terminalNumber' => $terminalNumber,
            'serviceCode' => $serviceCode,
            'serviceType' => $serviceType,
            'billId' => $billId,
            'paymentId' => $paymentId,
            'pans' => ''
        ), $token);
    }

    static function confirmTransaction(
        string $invoiceId,
        string $urlId,
        string $token
    ) {
        return self::post('/api/payment/confirm-transactions', array(
            'invoice' => $invoiceId,
            'urlId' => $urlId,
        ), $token);
    }

    /** Experimental; not tested yet. */
    static function verifyTransaction(
        bool   $returnMoreDetails,
        string $invoiceId,
        string $urlId,
        string $token
    ) {
        return self::post(
            $returnMoreDetails ? '/api/payment/verify-payment' : '/api/payment/verify-transactions'
            , array(
            'invoice' => $invoiceId,
            'urlId' => $urlId,
        ), $token);
    }

    /** Experimental; not tested yet. */
    static function reverseTransaction(
        string $invoiceId,
        string $urlId,
        string $token
    ) {
        return self::post('/api/payment/reverse-transactions', array(
            'invoice' => $invoiceId,
            'urlId' => $urlId,
        ), $token);
    }

    /** Experimental; not tested yet. */
    static function paymentInquiryV1(
        string $invoiceId,
        string $token
    ) {
        return self::post('/api/payment/payment-inquiry', array(
            'invoiceId' => $invoiceId,
        ), $token);
    }

    /** Experimental; not tested yet. */
    static function paymentInquiryV2(
        string $invoiceId,
        string $terminalNumber,
        string $token
    ) {
        return self::post('/api/v2/payment/payment-inquiry', array(
            'invoiceId' => $invoiceId,
            'terminalNumber' => $terminalNumber,
        ), $token);
    }
}
