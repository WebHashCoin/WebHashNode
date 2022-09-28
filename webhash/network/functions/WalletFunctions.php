<?php

namespace WebHash\Network\Functions;

use WebHash\Network\Cryptography\SHA3;

class WalletFunctions
{
    public static function generateWallet() : array
    {
        $args = [
            "curve_name"       => "secp256k1",
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ];
        $res = openssl_pkey_new($args);
        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res);
        $publicKey = $publicKey["key"];
        //strip the header and footer
        $publicKey = self::getStrippedPublicKey($publicKey);
        $privateKey = self::getStrippedPrivateKey($privateKey);
        return [
            "privateKey" => $privateKey,
            "publicKey" => $publicKey,
            "address" => self::generateAddress($publicKey)
        ];
    }

    //remove -----BEGIN PUBLIC KEY----- and -----END PUBLIC KEY----- from public key
    public static function getStrippedPublicKey(string $publicKey) : string
    {
        $publicKey = str_replace("-----BEGIN PUBLIC KEY-----", "", $publicKey);
        $publicKey = str_replace("-----END PUBLIC KEY-----", "", $publicKey);
        return str_replace("\n", "", $publicKey);
    }

    //remove -----BEGIN PRIVATE KEY----- and -----END PRIVATE KEY----- from private key
    public static function getStrippedPrivateKey(string $privateKey) : string
    {
        $privateKey = str_replace("-----BEGIN EC PRIVATE KEY-----", "", $privateKey);
        $privateKey = str_replace("-----END EC PRIVATE KEY-----", "", $privateKey);
        return str_replace("\n", "", $privateKey);
    }

    //add -----BEGIN PUBLIC KEY----- and -----END PUBLIC KEY----- to public key
    public static function getFormattedPublicKey(string $publicKey) : string
    {
        return "-----BEGIN PUBLIC KEY-----\n".$publicKey."\n-----END PUBLIC KEY-----";
    }

    //add -----BEGIN PRIVATE KEY----- and -----END PRIVATE KEY----- to private key
    public static function getFormattedPrivateKey(string $privateKey) : string
    {
        return "-----BEGIN EC PRIVATE KEY-----\n".$privateKey."\n-----END EC PRIVATE KEY-----";
    }

    public static function generateAddress(string $publicKey) : string
    {
        try {
            $address = SHA3::hash($publicKey, 256);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $address = "";
        }
        return $address;
    }

    public static function sign(string $data, string $privateKey) : string
    {
        $formattedPrivateKey = self::getFormattedPrivateKey($privateKey);
        openssl_sign($data, $signature, $formattedPrivateKey, OPENSSL_ALGO_SHA256);
        return $signature;
    }

    public static function verify(string $data, string $signature ,string $publicKey) : bool
    {
        return openssl_verify($data, $signature, self::getFormattedPublicKey($publicKey), OPENSSL_ALGO_SHA256) === 1;
    }
}
