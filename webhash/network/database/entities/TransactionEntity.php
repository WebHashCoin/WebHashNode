<?php

namespace WebHash\Network\Database\entities;

use PDO;
use WebHash\Helper\Result;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Functions\TransactionFunctions;

class TransactionEntity extends AbstractEntity
{
    public function getUnconfirmedTransactions() : array
    {
        $stmt = $this->db->prepare("SELECT * FROM `unconfirmed_transactions`");
        $stmt->execute();
        //bind to Transaction class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Transaction::class);
        return $stmt->fetchAll();
    }
    public function getWaitingTransactions(int $limit = -1) : array
    {
        //order by fee DESC and timestamp ASC
        if($limit == -1) {
            $stmt = $this->db->prepare("SELECT * FROM `waiting_transactions` ORDER BY fee DESC, timestamp");
        } else {
            $stmt = $this->db->prepare("SELECT * FROM `waiting_transactions` ORDER BY fee DESC, timestamp LIMIT :limit");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        //bind to Transaction class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Transaction::class);
        return $stmt->fetchAll();
    }

    public function getTransactionsByBlockId(string $blockId) : array
    {
        $stmt = $this->db->prepare("SELECT * FROM `transactions` WHERE block_id = :block_id");
        $stmt->execute(['block_id' => $blockId]);
        //bind to Transaction class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Transaction::class);
        return $stmt->fetchAll();
    }

    public function getTransactionCount() : int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `transactions`");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getUnconfirmedTransactionsCount() : int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `unconfirmed_transactions`");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function removeWaitingTransaction($waitingTransaction) : Result
    {
        $stmt = $this->db->prepare("DELETE FROM `waiting_transactions` WHERE id = :id");
        $state = $stmt->execute(['id' => $waitingTransaction->getId()]);
        if(!$state) {
            return Result::error("Could not remove waiting transaction");
        }
        return Result::success();
    }

    public function moveWaitingTransactionToUnconfirmed($waitingTransaction) : Result
    {
        //copy waiting transaction to unconfirmed transactions just using select
        $stmt = $this->db->prepare("INSERT INTO `unconfirmed_transactions` SELECT * FROM `waiting_transactions` WHERE id = :id");
        $inserted = $stmt->execute(['id' => $waitingTransaction->getId()]);
        if(!$inserted) {
            return Result::error("Could not move waiting transaction to unconfirmed: ".$stmt->errorInfo()[2]);
        }
        //remove waiting transaction
        $result = $this->removeWaitingTransaction($waitingTransaction);
        if(!$result->isSuccessful()) {
            return $result;
        }
        return Result::success();
    }

    public function addTransaction(Transaction $transaction,bool $skipVerify=false) : Result
    {
        $isReward = $transaction->getType() == Transaction::TYPE_REWARD;
        if(!$skipVerify) {
            $result = TransactionFunctions::verifyTransaction($transaction);
            if(!$result->isSuccessful()) {
                return Result::error("Transaction verification failed: ".$result->getMessage());
            }
        }
        //check if transaction already exists in unconfirmed_transactions or waiting_transactions or transactions
        //make one joined query
        $stmt = $this->db->prepare("SELECT * FROM `unconfirmed_transactions` WHERE id = :id
                                    UNION
                                    SELECT * FROM `waiting_transactions` WHERE id = :id
                                    UNION
                                    SELECT * FROM `transactions` WHERE id = :id");
        $stmt->execute(['id' => $transaction->getId()]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, Transaction::class);
        $result = $stmt->fetch();
        if($result) {
            return Result::error("Transaction already exists in database");
        }

        $table = 'waiting_transactions';
        if($isReward || $skipVerify) {
            $table = 'unconfirmed_transactions';
        }
        $stmt = $this->db->prepare("INSERT INTO $table (id, sender, recipient, type, amount, fee, timestamp, signature, block_id, nonce) VALUES (:id, :sender, :recipient, :type, :amount, :fee, :timestamp, :signature, :block_id, :nonce)");
        $state = $stmt->execute([
            'id' => $transaction->getId(),
            'sender' => $transaction->getSender(),
            'recipient' => $transaction->getRecipient(),
            'type' => $transaction->getType(),
            'amount' => $transaction->getAmount(),
            'fee' => $transaction->getFee(),
            'timestamp' => $transaction->getTimestamp(),
            'signature' => $transaction->getSignature(),
            'block_id' => $transaction->getBlockId(),
            'nonce' => $transaction->getNonce()
        ]);
        if(!$state) {
            return Result::error("Could not add transaction: ".$stmt->errorInfo()[2]);
        }
        return Result::success();
    }

    public function getWaitingTransactionsOfWallet(string $transactionSender)
    {
        $stmt = $this->db->prepare("SELECT * FROM `waiting_transactions` WHERE sender = :sender");
        $stmt->execute(['sender' => $transactionSender]);
        //bind to Transaction class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Transaction::class);
        return $stmt->fetchAll();
    }

    /**
     * return sum of amount and fee of all waiting transactions of a wallet
     *
     * @param string $transactionSender
     * @param string|null $excludeTransaction
     * @return int
     */
    public function getWaitingTransactionsSumOfWallet(string $transactionSender, string $excludeTransaction = null) : int
    {
        $stmt = $this->db->prepare("SELECT SUM(amount + fee) FROM `waiting_transactions` WHERE sender = :sender".($excludeTransaction ? " AND id != :id" : ""));
        $params = ['sender' => $transactionSender];
        if($excludeTransaction) {
            $params['id'] = $excludeTransaction;
        }
        $stmt->execute($params);
        $response = $stmt->fetchColumn();
        if($response == null) {
            return 0;
        } else {
            return $response;
        }
    }

    public function getUnconfirmedTransactionsOfWallet(string $transactionSender)
    {
        $stmt = $this->db->prepare("SELECT * FROM `unconfirmed_transactions` WHERE sender = :sender");
        $stmt->execute(['sender' => $transactionSender]);
        //bind to Transaction class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Transaction::class);
        return $stmt->fetchAll();
    }

    /**
     * return sum of amount and fee of all unconfirmed transactions of a wallet
     * this also excludes any mining reward transactions
     *
     * @param string $transactionSender
     * @param string|null $excludeTransaction
     * @return int
     */
    public function getUnconfirmedTransactionsSumOfWallet(string $transactionSender, string $excludeTransaction = null) : int
    {
        $stmt = $this->db->prepare("SELECT SUM(amount + fee) FROM `unconfirmed_transactions` WHERE sender = :sender AND type != 1 ".($excludeTransaction ? " AND id != :id" : ""));
        $params = ['sender' => $transactionSender];
        if($excludeTransaction) {
            $params['id'] = $excludeTransaction;
        }
        $stmt->execute($params);
        $response = $stmt->fetchColumn();
        if($response == null) {
            return 0;
        } else {
            return $response;
        }
    }

    public function removeUnconfirmedTransaction(Transaction $transaction) : Result
    {
        $stmt = $this->db->prepare("DELETE FROM `unconfirmed_transactions` WHERE id = :id");
        $state = $stmt->execute(['id' => $transaction->getId()]);
        if(!$state) {
            return Result::error("Could not remove unconfirmed transaction: ".$stmt->errorInfo()[2]);
        }
        return Result::success();
    }

    public function getTransactionById($searchValue) :?Transaction
    {
        $stmt = $this->db->prepare("SELECT * FROM `transactions` WHERE id = :id");
        $stmt->execute(['id' => $searchValue]);
        //bind to Transaction class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Transaction::class);
        $result = $stmt->fetch();
        if(!$result) {
            return null;
        }
        return $result;
    }

    public function getTransactionsByAddress(string $address) : array
    {
        //get public_key from address
        $walletEntity = new WalletEntity($this->db);
        $wallet = $walletEntity->getWallet($address);
        $publicKey = $wallet->getPublicKey();
        if($publicKey == null) {
            $publicKey = $address;
        }
        $stmt = $this->db->prepare("SELECT * FROM `transactions` WHERE sender = :public_key OR recipient = :address");
        $stmt->execute(['public_key' => $publicKey, 'address' => $address]);
        //bind to Transaction class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Transaction::class);
        return $stmt->fetchAll();
    }

    public function getConfirmations(Transaction $transaction) : int {
        //get block
        $blockEntity = new BlockEntity($this->db);
        $block = $blockEntity->getBlockById($transaction->getBlockId());
        if(!$block) {
            return -1;
        }
        //get last block
        $lastBlock = $blockEntity->getLatestBlock();
        if(!$lastBlock) {
            return -1;
        }
        return $lastBlock->getHeight() - $block->getHeight();
    }



    public function reverseTransaction(Transaction $transaction) : Result
    {
        //get sender and recipient wallet
        $walletEntity = new WalletEntity($this->db);
        //reverse transaction
        $success = $walletEntity->reverseAccountBalanceByTransaction($transaction);
        if(!$success->isSuccessful()) {
            return Result::error("Could not reverse transaction: could not reverse account balance");
        }
        //remove transaction from transactions table
        $stmt = $this->db->prepare("DELETE FROM `transactions` WHERE id = :id");
        $state = $stmt->execute(['id' => $transaction->getId()]);
        if(!$state) {
            return Result::error("Could not remove transaction from transactions table: ".$stmt->errorInfo()[2]);
        }
        return Result::success();
    }
}
