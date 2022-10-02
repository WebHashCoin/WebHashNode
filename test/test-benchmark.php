<?php

use WebHash\Network\Cryptography\Algorithms\AlgorithmArgon2id;
use WebHash\Network\Cryptography\SHA3;
use WebHash\Network\Functions\WalletFunctions;

require_once __DIR__ . '/../vendor/autoload.php';

//benchmark crypto functions
echo "##########################################\r\n";
echo "Benchmarking crypto functions...\r\n";


//start keccak256 benchmark
$hash = "startingString";
$start = microtime(true);
$times = 1000000;
for($i = 0; $i < $times; $i++) {
    try {
        $hash = SHA3::hash($hash, 256);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
$end = microtime(true);
echo "SHA3-256 x $times: " . ($end - $start) . ' seconds' . PHP_EOL;

//start argon2id benchmark
$hash = "startingString";
$start = microtime(true);
$times = 100;
$argon2id = new AlgorithmArgon2id();
for($i = 0; $i < $times; $i++) {
    //start argon2id hash and verify
    $argonHash = $argon2id->hash($hash);
    //verify argon2id hash
    $bool = $argon2id->verify($hash, $argonHash);
    if(!$bool) {
        die("Argon validations failed after: $i iterations");
    }
}
$end = microtime(true);
echo "Argon2id(hash/verify) x $times: " . ($end - $start) . ' seconds' . PHP_EOL;


//benchmark wallet generation
$start = microtime(true);
$times = 10;
for($i = 0; $i < $times; $i++) {
    $wallet = WalletFunctions::generateWallet();
}
$end = microtime(true);
echo "Wallet generation x $times: " . ($end - $start) . ' seconds' . PHP_EOL;
