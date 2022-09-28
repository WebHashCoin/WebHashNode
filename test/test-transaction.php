<?php
//autoloader and initialization for controller endpoints
use WebHash\Network\Cryptography\Base58;
use WebHash\Network\Cryptography\SHA3;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Database\entities\TransactionEntity;
use WebHash\Network\Database\entities\WalletEntity;
use WebHash\Network\Functions\WalletFunctions;
use WebHash\WebHash;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . './test-wallet.php';

//if argument is set, then loop for x times
$loop = $argv[1] ?? 1;
$amount = $argv[2] ?? 1;
$webHash = new WebHash();
$webHash->init();

$walletEntity = new WalletEntity(WebHash::getDatabaseConnection());


$failed = 0;
$success = 0;
for($i = 0; $i < $loop; $i++) {
    $transaction = new Transaction();
    $transaction->setSender(PUBLIC_KEY);
    $receiver = 'dcd8b314a936aace1266b561a3f2737514ef2f22bb50dd1b3b94157b01660ba8';
    $transaction->setRecipient($receiver);
    $transaction->setAmount($amount);
    $transaction->setFee(0.1);
    $transaction->setTimestamp(time());
    try {
        $nonce = random_int(0, 99999999999999999);
    } catch (Exception $e) {
        $nonce = 0;
        error_log($e->getMessage());
    }
    $transaction->setNonce($nonce);
    $data = $transaction->getTransactionData();
    try {
        $transaction->setId(SHA3::hash($data, 256));
    } catch (Exception $e) {
        error_log($e->getMessage());
        return;
    }
    $transaction->setSignature(Base58::encode(WalletFunctions::sign($data, $privateKey)));
    $transactionEntity = new TransactionEntity(WebHash::getDatabaseConnection());
    $result = $transactionEntity->addTransaction($transaction);

    //echo 50 chars
    echo str_pad('', 100) . "\r";
    if($result->isSuccessful()) {
        echo "Transaction $i added successfully\r";
        $success++;
    } else {
        echo "Transaction $i failed: ".$result->getMessage(). "\r";
        $failed++;
    }
}
$message = "Completed: $success successful, $failed failed";
echo str_pad($message, 100) . "\r\n";
