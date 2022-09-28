<?php

namespace WebHash\Network\Cryptography;

class Base58
{
    private static string $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    private static int $base = 58;

    /**
     * Encodes a string to base58
     *
     * @param string $input
     * @return null|string
     */
    public static function encode(string $input) : ?string
    {
        //check if input is not empty
        if(empty($input)) {
            return null;
        }
        //convert to hex by using 256 to base 10
        $hex = gmp_strval(gmp_init(bin2hex($input), 16), 10);
        //convert to base58
        $output = '';
        while(gmp_cmp($hex, 0) > 0) {
            $div = gmp_div_q($hex, self::$base, GMP_ROUND_ZERO);
            $mod = gmp_strval(gmp_mod($hex, self::$base));
            $output = self::$alphabet[gmp_intval($mod)] . $output;
            $hex = $div;
        }
        //add leading zeros
        for($i = 0; $i < strlen($input) && $input[$i] === "\0"; $i++) {
            $output = '1' . $output;
        }
        return $output;
    }

    /**
     * Decodes a base58 string to a string
     *
     * @param string $input
     * @return null|string
     */
    public static function decode(string $input) : ?string
    {
        //check if input is not empty
        if(empty($input)) {
            return null;
        }
        //convert to base 10
        $hex = gmp_strval(gmp_init(0, 10));
        for($i = 0; $i < strlen($input); $i++) {
            $hex = gmp_strval(gmp_add(gmp_mul($hex, self::$base), strpos(self::$alphabet, $input[$i])));
        }
        //convert to hex
        $hex = gmp_strval(gmp_init($hex, 10), 16);
        //add leading zeros
        if(strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $output = hex2bin($hex);
        //add leading zeros
        for($i = 0; $i < strlen($input) && $input[$i] === '1'; $i++) {
            $output = "\0" . $output;
        }
        return $output;
    }
}
