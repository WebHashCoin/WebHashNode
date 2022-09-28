<?php

namespace WebHash\Network\Database\entities;

use PDO;
use WebHash\Helper\Result;
use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\DAO\Node;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Functions\BlockFunctions;
use WebHash\Network\Functions\TransactionFunctions;
use WebHash\WebHash;

class NodeEntity extends AbstractEntity
{
    /*
            `id` VARCHAR(255) PRIMARY KEY UNIQUE NOT NULL,
            `status` INTEGER NOT NULL,
            `url` VARCHAR(255) NOT NULL UNIQUE,
            `public_key` VARCHAR(255) UNIQUE,
            `protocol` VARCHAR(15) NOT NULL,
            `version` VARCHAR(15),
            `block_hash` VARCHAR(255),
            `block_height` INTEGER DEFAULT 0,
            `connection_count` INTEGER DEFAULT 0,
            `peers_count` INTEGER DEFAULT 0,
            `transactions_count` INTEGER DEFAULT 0,
            `unconfirmed_transactions_count` INTEGER DEFAULT 0,
            `introduced_at` INTEGER DEFAULT (strftime(\'%s\', \'now\')) NOT NULL,
            `last_seen` INTEGER
     */
    public function addNode(Node $node)
    {

    }


    public function submitBlock(Block $block, array $transactions, Transaction $rewardTransaction): Result
    {
        //return error if block is not valid
        if (!BlockFunctions::verifyBlock($block,true, $transactions)) {
            return Result::error("Invalid block! Block is not valid");
        }
        //return error if reward transaction is not valid
        $result = TransactionFunctions::verifyTransaction($rewardTransaction);
        if (!$result->isSuccessful()) {
            return Result::error("Invalid reward transaction! Transaction is not valid");
        }
        //start transaction
        $this->db->beginTransaction();
        //lock unconfirmed transactions table
        $this->db->exec("LOCK TABLE unconfirmed_transactions");
        //the transactions should be set into unconfirmed_transactions and the old ones should be moved to waiting if not set in transactions
        $transactionEntity = new TransactionEntity(WebHash::getDatabaseConnection());
        $unconfirmedTransactions = $transactionEntity->getUnconfirmedTransactions();
        foreach ($unconfirmedTransactions as $transaction) {
            if(!in_array($transaction, $transactions)) {
                $state = $transactionEntity->addTransaction($transaction);
                if(!$state->isSuccessful()) {
                    $this->unlockAndRollback();
                    return $state;
                }
                //remove from unconfirmed transactions
                $state = $transactionEntity->removeUnconfirmedTransaction($transaction);
                if(!$state->isSuccessful()) {
                    $this->unlockAndRollback();
                    return $state;
                }
            }
        }
        //add left transactions to unconfirmed transactions
        foreach ($transactions as $transaction) {
            if($transaction instanceof Transaction) {
                if(!in_array($transaction, $unconfirmedTransactions)) {
                    $state = $transactionEntity->addTransaction($transaction,true);
                    if(!$state->isSuccessful()) {
                        $this->unlockAndRollback();
                        return $state;
                    }
                }
            }
        }

        //check if block is already in the database
        $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
        $success = $blockEntity->addBlock($block, $rewardTransaction);
        if(!$success->isSuccessful()) {
            $this->unlockAndRollback();
            return $success;
        }
        //unlock unconfirmed transactions table
        $this->db->exec("UNLOCK TABLE unconfirmed_transactions");
        //commit transaction
        $this->db->commit();
        return Result::success();
    }

    private function unlockAndRollback() {
        $this->db->exec("UNLOCK TABLE unconfirmed_transactions");
        $this->db->rollBack();
    }

    public function getNodeByPublicKey($publicKey): ?Node
    {
        $stmt = $this->db->prepare("SELECT * FROM nodes WHERE public_key = :public_key");
        $stmt->execute(['public_key' => $publicKey]);
        $result = $stmt->fetch(PDO::FETCH_CLASS, Node::class);
        if ($result instanceof Node) {
            return $result;
        }
        return null;
    }

    public function getNodeByUrl($url): ?Node
    {
        $stmt = $this->db->prepare("SELECT * FROM nodes WHERE url = :url");
        $stmt->execute(array(":url" => $url));
        $result = $stmt->fetch(PDO::FETCH_CLASS, Node::class);
        if ($result instanceof Node) {
            return $result;
        }
        return null;
    }

    public function getNodeCount() : int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM nodes");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function submitTransaction(Transaction $transaction) : Result
    {
        $transactionEntity = new TransactionEntity(WebHash::getDatabaseConnection());
        $result = $transactionEntity->addTransaction($transaction, true);
        if(!$result->isSuccessful()) {
            return $result;
        }
        return Result::success();
    }
}
