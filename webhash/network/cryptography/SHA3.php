<?php

namespace WebHash\Network\Cryptography;

use Exception;
use function mb_strlen;
use function mb_substr;

class SHA3
{
    /**
     * @throws Exception
     */
    public static function hash(string $data, int $bits = 256) : string
    {
        if($bits !== 256 && $bits !== 512) {
            throw new Exception('Invalid hash length');
        }
        return hash('sha3-' . $bits, $data);
    }

    /**
     * @throws Exception
     */
    public static function verify(string $data, string $hash, int $bits = 256) : bool
    {
        return self::hash("sha3-" . $bits . $data) === $hash;
    }
}
