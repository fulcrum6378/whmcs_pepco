<?php /** @noinspection PhpUnused */

class RSA {
    static function rsa_encrypt($message, $public_key, $modulus, $keyLength): string {
        $padded = RSA::add_PKCS1_padding($message, true, $keyLength / 8);
        $number = RSA::binary_to_number($padded);
        $encrypted = RSA::pow_mod($number, $public_key, $modulus);
        return RSA::number_to_binary($encrypted, $keyLength / 8);
    }

    static function rsa_decrypt($message, $private_key, $modulus, $keyLength) {
        $number = RSA::binary_to_number($message);
        $decrypted = RSA::pow_mod($number, $private_key, $modulus);
        $result = RSA::number_to_binary($decrypted, $keyLength / 8);
        return RSA::remove_PKCS1_padding($result, $keyLength / 8);
    }

    static function rsa_sign($message, $private_key, $modulus, $keyLength): string {
        $padded = RSA::add_PKCS1_padding($message, false, $keyLength / 8);
        $number = RSA::binary_to_number($padded);
        $signed = RSA::pow_mod($number, $private_key, $modulus);
        return RSA::number_to_binary($signed, $keyLength / 8);
    }

    static function rsa_verify($message, $public_key, $modulus, $keyLength) {
        return RSA::rsa_decrypt($message, $public_key, $modulus, $keyLength);
    }

    static function rsa_kyp_verify($message, $public_key, $modulus, $keyLength) {
        $number = RSA::binary_to_number($message);
        $decrypted = RSA::pow_mod($number, $public_key, $modulus);
        $result = RSA::number_to_binary($decrypted, $keyLength / 8);
        return RSA::remove_KYP_padding($result, $keyLength / 8);
    }

    static function pow_mod($p, $q, $r): ?string {
        $factors = array();
        $div = $q;
        $power_of_two = 0;
        while (bccomp($div, "0") == 1) {
            $rem = bcmod($div, 2);
            $div = bcdiv($div, 2);
            if ($rem) $factors[] = $power_of_two;
            $power_of_two++;
        }
        $partial_results = array();
        $part_res = $p;
        $idx = 0;
        foreach ($factors as $factor) {
            while ($idx < $factor) {
                $part_res = bcpow($part_res, "2");
                $part_res = bcmod($part_res, $r);
                $idx++;
            }
            $partial_results[] = $part_res;
        }
        $result = "1";
        foreach ($partial_results as $part_res) {
            $result = bcmul($result, $part_res);
            $result = bcmod($result, $r);
        }
        return $result;
    }

    static function add_PKCS1_padding($data, $isPublicKey, $blockSize): string {
        $pad_length = $blockSize - 3 - strlen($data);
        if ($isPublicKey) {
            $block_type = "\x02";
            $padding = "";
            for ($i = 0; $i < $pad_length; $i++) {
                $rnd = mt_rand(1, 255);
                $padding .= chr($rnd);
            }
        } else {
            $block_type = "\x01";
            $padding = str_repeat("\xFF", $pad_length);
        }
        return "\x00" . $block_type . $padding . "\x00" . $data;
    }

    static function remove_PKCS1_padding($data, $blockSize) {
        assert(strlen($data) == $blockSize);
        $data = substr($data, 1);
        if ($data[0] == '\0')
            die("Block type 0 not implemented.");
        assert(($data[0] == "\x01") || ($data[0] == "\x02"));
        $offset = strpos($data, "\0", 1);
        return substr($data, $offset + 1);
    }

    static function remove_KYP_padding($data, $blockSize) {
        assert(strlen($data) == $blockSize);
        $offset = strpos($data, "\0");
        return substr($data, 0, $offset);
    }

    static function binary_to_number($data): string {
        $base = "256";
        $radix = "1";
        $result = "0";
        for ($i = strlen($data) - 1; $i >= 0; $i--) {
            $digit = ord($data[$i]);
            $part_res = bcmul($digit, $radix);
            $result = bcadd($result, $part_res);
            $radix = bcmul($radix, $base);
        }
        return $result;
    }

    static function number_to_binary($number, $blockSize): string {
        $base = "256";
        $result = "";
        $div = $number;
        while ($div > 0) {
            $mod = bcmod($div, $base);
            $div = bcdiv($div, $base);
            $result = chr($mod) . $result;
        }
        return str_pad($result, $blockSize, "\x00", STR_PAD_LEFT);
    }
}

class RSAProcessor {
    private /*string*/ $public_key;
    private /*?string*/ $private_key;
    private /*?string*/ $modulus;
    private $key_length;

    public function __construct(string $xmlRsaKey, ?int $type = null) {
        if ($type == RSAKeyType::XMLFile)
            $xmlObj = simplexml_load_file($xmlRsaKey);
        else
            $xmlObj = simplexml_load_string($xmlRsaKey);
        $this->modulus = RSA::binary_to_number(base64_decode($xmlObj->Modulus));
        $this->public_key = RSA::binary_to_number(base64_decode($xmlObj->Exponent));
        $this->private_key = RSA::binary_to_number(base64_decode($xmlObj->D));
        $this->key_length = strlen(base64_decode($xmlObj->Modulus)) * 8;
    }

    public function getPublicKey(): ?string {
        return $this->public_key;
    }

    public function getPrivateKey(): ?string {
        return $this->private_key;
    }

    public function getKeyLength() {
        return $this->key_length;
    }

    public function getModulus(): ?string {
        return $this->modulus;
    }

    public function encrypt($data): string {
        return base64_encode(RSA::rsa_encrypt($data, $this->public_key, $this->modulus, $this->key_length));
    }

    public function decrypt($data) {
        return RSA::rsa_decrypt($data, $this->private_key, $this->modulus, $this->key_length);
    }

    public function sign($data): string {
        return RSA::rsa_sign($data, $this->private_key, $this->modulus, $this->key_length);
    }

    public function verify($data) {
        return RSA::rsa_verify($data, $this->public_key, $this->modulus, $this->key_length);
    }
}

class RSAKeyType {
    const XMLFile = 0;
    const XMLString = 1;
}
