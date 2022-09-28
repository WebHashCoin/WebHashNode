<?php

require_once __DIR__ . '/../vendor/autoload.php';

$filename = 'test-wallet.json';
if(!file_exists($filename)) {
    $wallet = \WebHash\Network\Functions\WalletFunctions::generateWallet();
    $publicKey = $wallet['publicKey'];
    $privateKey = $wallet['privateKey'];
    $file = fopen($filename, 'w');
    fwrite($file, json_encode($wallet));
} else {
    $file = fopen($filename, 'r');
    $wallet = json_decode(fgets($file), true);
    $publicKey = $wallet['publicKey'];
    $privateKey = $wallet['privateKey'];
}
fclose($file);
//set global wallet constants
define('PUBLIC_KEY', $publicKey);
define('PRIVATE_KEY', $privateKey);
define('ADDRESS', \WebHash\Network\Functions\WalletFunctions::generateAddress($publicKey));

