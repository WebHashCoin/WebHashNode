<?php

namespace WebHash\Network\Functions;

use Exception;
use WebHash\Helper\Result;
use WebHash\Network\Cryptography\Base58;
use WebHash\Network\Cryptography\SHA3;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Database\entities\MiningInfoEntity;
use WebHash\Network\Database\entities\TransactionEntity;
use WebHash\Network\Database\entities\WalletEntity;
use WebHash\WebHash;

class TransactionFunctions
{


    public static function verifyTransaction(Transaction $transaction): Result
    {
        $isReward = $transaction->getType() == Transaction::TYPE_REWARD;
        $transactionId = $transaction->getId();
        $transactionSender = $transaction->getSender();
        $transactionSenderAddress = WalletFunctions::generateAddress($transactionSender);
        $transactionRecipient = $transaction->getRecipient();
        $transactionAmount = $transaction->getAmount();
        $transactionFee = $transaction->getFee();
        $fullTransactionAmount = $transactionAmount + $transactionFee;
        $transactionSignature = $transaction->getSignature();
        $transactionTimestamp = $transaction->getTimestamp();

        //timestamp cannot be in the future ( add 1 minute to allow for network latency )
        if($transactionTimestamp > time() + 60) {
            return Result::error('Transaction is in the future');
        }

        //check sum of waiting and pending transactions
        $transactionEntity = new TransactionEntity(WebHash::getDatabaseConnection());

        $waitingTransactionsSum = $transactionEntity->getWaitingTransactionsSumOfWallet($transactionSender, $transactionId);
        $unconfirmedTransactionsSum = $transactionEntity->getUnconfirmedTransactionsSumOfWallet($transactionSender, $transactionId);
        $pendingTransactionsSum = $waitingTransactionsSum + $unconfirmedTransactionsSum;

        if ($transactionTimestamp > time()) {
            return Result::error("Timestamp is in the future");
        }

        if(!$isReward) {
            $walletEntity = new WalletEntity(WebHash::getDatabaseConnection());
            $transactionSenderBalance = $walletEntity->getBalance($transactionSenderAddress);
            $transactionSenderBalance -= $pendingTransactionsSum;

            //check if sender balance is smaller than transaction amount + fee with accurate precision
            if (bccomp($transactionSenderBalance, $fullTransactionAmount, 8) == -1) {
                return Result::error("Sender balance($transactionSenderBalance) is smaller than transaction $fullTransactionAmount");
            }
            if ($transactionSenderAddress == $transactionRecipient) {
                return Result::error("Sender and receiver are the same");
            }

            if ($transactionAmount <= 0) {
                return Result::error("Amount is less than or equal to 0");
            }

            if ($transactionFee <= 0) {
                return Result::error("Fee is less than or equal to 0");
            }
        } else {
            $miningInfoEntity = new MiningInfoEntity(WebHash::getDatabaseConnection());
            $miningInfo = $miningInfoEntity->getLatestMiningInfo();
            if($miningInfo != null) {
                if($transactionAmount != $miningInfo->getReward()) {
                    return Result::error("Reward is not correct");
                }
            }

            if($transactionFee != 0) {
                return Result::error("Fee is not 0");
            }
        }

        $hash = '';
        try {
            $hash = SHA3::hash($transaction->getTransactionData(), 256);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
        if ($transactionId != $hash) {
            return Result::error("Hash is not valid");
        }

        if (!WalletFunctions::verify($transaction->getTransactionData(), Base58::decode($transactionSignature), $transactionSender)) {
            return Result::error("Signature is not valid");
        }

        return Result::success();
    }
}
