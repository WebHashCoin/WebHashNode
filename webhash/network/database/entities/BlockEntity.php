<?php

namespace WebHash\Network\Database\entities;

use PDO;
use WebHash\Helper\Result;
use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\dao\MiningInfo;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Database\DAO\Wallet;
use WebHash\Network\Database\DatabaseConnection;
use WebHash\Network\Functions\BlockFunctions;
use WebHash\Network\Functions\TransactionFunctions;
use WebHash\Network\Functions\WalletFunctions;
use WebHash\WebHash;

class BlockEntity extends AbstractEntity
{

    private function stmtToBlock($stmt) : ?Block
    {
        $object = $stmt->fetchObject(Block::class);
        if($object instanceof Block) {
            return $object;
        } else {
            return null;
        }
    }

    public function getBlockById($id) : ?Block
    {
        $stmt = $this->db->prepare("SELECT * FROM blocks WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $this->stmtToBlock($stmt);
    }

    public function getBlocks($limit, $fromHeight = -1) : array
    {
        if($fromHeight == -1) {
            $stmt = $this->db->prepare("SELECT * FROM blocks ORDER BY height DESC LIMIT :limit");
            $stmt->setFetchMode(PDO::FETCH_CLASS, Block::class);
            $stmt->execute(['limit' => $limit]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM blocks WHERE height <= :fromHeight ORDER BY height DESC LIMIT :limit");
            $stmt->setFetchMode(PDO::FETCH_CLASS, Block::class);
            $stmt->execute(['limit' => $limit, 'fromHeight' => $fromHeight]);
        }
        //fetch as Block
        return $stmt->fetchAll();
    }

    public function getBlockByHeight($height) : ?Block
    {
        $stmt = $this->db->prepare("SELECT * FROM blocks WHERE height = :height");
        $stmt->execute(['height' => $height]);
        return $this->stmtToBlock($stmt);
    }

    public function getLatestBlock() : ?Block
    {
        $stmt = $this->db->prepare("SELECT * FROM blocks ORDER BY height DESC LIMIT 1");
        $stmt->execute();
        return $this->stmtToBlock($stmt);
    }

    public function addBlock(Block $block, Transaction $rewardTransaction) : Result
    {
        //verify block
        if(!BlockFunctions::verifyBlock($block, true)) {
            return Result::error("Block verification failed");
        }

        //check if block already exists
        if($this->getBlockById($block->getId()) instanceof Block) {
            return Result::error("Block already exists");
        }

        //check if block height already exists
        if($this->getBlockByHeight($block->getHeight()) instanceof Block) {
            return Result::error("Block height already exists");
        }

        //check if the difficulty is correct
        $calcDifficulty = BlockFunctions::calculateDifficulty($block->getHeight()-1);
        if($calcDifficulty != $block->getDifficulty()) {
            return Result::error("Block difficulty is not correct");
        }

        //start transaction
        $this->db->beginTransaction();
        //lock blocks table
        $this->db->exec("LOCK TABLES blocks WRITE");

        //add block to blocks
        $stmt = $this->db->prepare("INSERT INTO blocks (id, height, nonce, timestamp, generator, difficulty,reward_transaction_id, transactions,transactions_hash, signature, algorithm, algorithm_hash, previous_block_id) VALUES (:id, :height, :nonce, :timestamp, :generator, :difficulty,:reward_transaction_id, :transactions,:transactions_hash, :signature, :algorithm, :algorithm_hash, :previous_block_id)");
        $result = $stmt->execute([
            'id' => $block->getId(),
            'height' => $block->getHeight(),
            'nonce' => $block->getNonce(),
            'timestamp' => $block->getTimestamp(),
            'generator' => $block->getGenerator(),
            'difficulty' => $block->getDifficulty(),
            'reward_transaction_id' => $block->getRewardTransactionId(),
            'transactions' => $block->getTransactions(),
            'transactions_hash' => $block->getTransactionsHash(),
            'signature' => $block->getSignature(),
            'algorithm' => $block->getAlgorithm(),
            'algorithm_hash' => $block->getAlgorithmHash(),
            'previous_block_id' => $block->getPreviousBlockId()
        ]);
        if (!$result) {
            $this->rollback();
            return Result::error("Error adding block to blocks table: ". $stmt->errorInfo()[2]);
        }
        //add reward transaction to transactions
        $walletEntity = new WalletEntity($this->db);
        $transactionEntity = new TransactionEntity($this->db);
        //add reward transaction to unconfirmed transactions
        $result = $transactionEntity->addTransaction($rewardTransaction);
        if(!$result->isSuccessful()) {
            $this->rollback();
            return $result;
        }
        //move rows from unconfirmed_transactions to transactions and delete them from unconfirmed_transactions
        $unconfirmedTransactions = $transactionEntity->getUnconfirmedTransactions();
        foreach ($unconfirmedTransactions as $unconfirmedTransaction) {
            $result = TransactionFunctions::verifyTransaction($unconfirmedTransaction);
            if(!$result->isSuccessful()) {
                $this->rollback();
                return Result::error("Transaction(unconfirmed_transactions) verification failed. ". $result->getMessage());
            }
            //db insert into transactions and delete at the same time from unconfirmed_transactions
            $stmt = $this->db->prepare("INSERT INTO transactions (id,nonce,sender,recipient,type,amount,fee,signature,timestamp,block_id)
                                              SELECT id,nonce,sender,recipient,type,amount,fee,signature,timestamp,:block_id FROM unconfirmed_transactions WHERE id = :id");
            $result = $stmt->execute([
                'id' => $unconfirmedTransaction->getId(),
                'block_id' => $block->getId()
            ]);
            if (!$result) {
                $this->rollback();
                return Result::error("Error moving unconfirmed transaction to transactions table: ". $stmt->errorInfo()[2]);
            }
            //Update Account Balance by Transaction
            $result = $walletEntity->updateAccountBalanceByTransaction($unconfirmedTransaction);
            if (!$result->isSuccessful()) {
                $this->rollback();
                return Result::error("Error updating account balance. E: ".$result->getMessage());
            }
            //delete from unconfirmed_transactions
            $stmt = $this->db->prepare("DELETE FROM unconfirmed_transactions WHERE id = :id");
            $result = $stmt->execute([
                'id' => $unconfirmedTransaction->getId()
            ]);
            if (!$result) {
                $this->rollback();
                return Result::error("Error deleting unconfirmed transaction from unconfirmed_transactions table: ". $stmt->errorInfo()[2]);
            }
        }
        //move rows from waiting_transactions to unconfirmed_transactions but before check each transaction for validity
        $transactionEntity = new TransactionEntity($this->db);
        $waitingTransactions = $transactionEntity->getWaitingTransactions(WebHash::$block_transaction_size);
        foreach ($waitingTransactions as $waitingTransaction) {
            $result = TransactionFunctions::verifyTransaction($waitingTransaction);
            if(!$result->isSuccessful()) {
                error_log("Transaction(waiting_transactions) verification failed");
                //remove transaction from waiting_transactions
                $transactionEntity->removeWaitingTransaction($waitingTransaction);
                continue;
            }
            $result = $transactionEntity->moveWaitingTransactionToUnconfirmed($waitingTransaction);
            if (!$result->isSuccessful()) {
                $this->rollback();
                return Result::error("Error while moving a transaction from waiting_transactions to unconfirmed_transactions. E: ".$result->getMessage());
            }
        }

        //add mining_info to mining_info
        $miningInfo = new MiningInfo();
        $miningInfo->setBlockId($block->getId());
        $previousBlock = $this->getBlockById($block->getPreviousBlockId());
        if($previousBlock instanceof Block) {
            $newDifficulty = BlockFunctions::calculateDifficulty($block->getHeight());
        } else {
            $newDifficulty = WebHash::$mining_starting_difficulty;
        }
        $miningInfo->setDifficulty($newDifficulty);
        $miningInfo->setUnconfirmedTransactionsCount($transactionEntity->getUnconfirmedTransactionsCount());
        $miningInfo->setUnconfirmedTransactionsHash(BlockFunctions::getBlockTransactionsHash(null, true));
        $miningInfo->setReward(BlockFunctions::calculateReward($block));
        $miningInfoEntity = new MiningInfoEntity($this->db);
        $result = $miningInfoEntity->addMiningInfo($miningInfo);
        if (!$result) {
            $this->rollback();
            return Result::error("Error adding mining info to mining info table");
        }

        //add mined block to miners wallet
        $walletEntity = new WalletEntity($this->db);
        $address = WalletFunctions::generateAddress($block->getGenerator());
        $wallet = $walletEntity->getWallet($address);
        $wallet->setBlocksMined($wallet->getBlocksMined() + 1);
        $walletEntity->updateWallet($wallet);

        //unlock blocks table
        $this->db->exec("UNLOCK TABLES");
        //commit transaction
        $this->db->commit();

        error_log("Block added to database: " . $block->getId());
        return Result::success();
    }


    public function deleteBlockAtHeight(int $height) : Result {
        $block = $this->getBlockByHeight($height);
        if(!$block instanceof Block) {
            return Result::error("Block not found at height: $height");
        }
        return $this->deleteBlock($block);
    }

    public function deleteBlock(Block $block) : Result {
        if($block->getHeight() === 1) {
            return Result::error("Cannot delete genesis block");
        }
        //check if block is in Database
        $blockInDb = $this->getBlockById($block->getId());
        if(!$blockInDb instanceof Block) {
            return Result::error("Block not found in database");
        }
        //blocks to be deleted
        $blocksToDelete = [];
        $blocksToDelete[] = $blockInDb;
        //get all blocks after the block to be deleted
        $currentHeight = $this->getLatestBlock()->getHeight();
        for($i = $blockInDb->getHeight() + 1; $i <= $currentHeight; $i++) {
            $blocksToDelete[] = $this->getBlockByHeight($i);
        }
        //start exclusive transaction
        $this->db->beginTransaction();
        //exclusive mode
        $this->db->exec("SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE");
        //lock tables
        $this->db->exec("LOCK TABLES blocks WRITE, transactions WRITE, unconfirmed_transactions WRITE, waiting_transactions WRITE, mining_info WRITE, wallets WRITE");
        //reverse transactions
        $transactionEntity = new TransactionEntity($this->db);
        $walletEntity = new WalletEntity($this->db);
        foreach ($blocksToDelete as $blockToDelete) {
            $transactions = $transactionEntity->getTransactionsByBlockId($blockToDelete->getId());
            foreach ($transactions as $transaction) {
                $result = $transactionEntity->reverseTransaction($transaction);
                if (!$result->isSuccessful()) {
                    $this->rollback();
                    return Result::error("Error updating account balance. E: ".$result->getMessage());
                }
            }
            //delete block from blocks
            $stmt = $this->db->prepare("DELETE FROM blocks WHERE id = :id");
            $result = $stmt->execute([
                'id' => $blockToDelete->getId()
            ]);
            if (!$result) {
                $this->rollback();
                return Result::error("Error deleting block from blocks table: ". $stmt->errorInfo()[2]);
            }
            //delete block from mining_info
            $stmt = $this->db->prepare("DELETE FROM mining_info WHERE block_id = :block_id");
            $result = $stmt->execute([
                'block_id' => $blockToDelete->getId()
            ]);
            if (!$result) {
                $this->rollback();
                return Result::error("Error deleting block from mining_info table: ". $stmt->errorInfo()[2]);
            }
            //delete block from wallets
            $stmt = $this->db->prepare("DELETE FROM wallets WHERE block_id = :block_id");
            $result = $stmt->execute([
                'block_id' => $blockToDelete->getId()
            ]);
            if (!$result) {
                $this->rollback();
                return Result::error("Error deleting block from wallets table: ". $stmt->errorInfo()[2]);
            }
            //remove mined block from miners wallet
            $address = WalletFunctions::generateAddress($blockToDelete->getGenerator());
            $wallet = $walletEntity->getWallet($address);
            if($wallet instanceof Wallet) {
                $wallet->setBlocksMined($wallet->getBlocksMined() - 1);
                $walletEntity->updateWallet($wallet);
            }
        }
        //unlock blocks table
        $this->db->exec("UNLOCK TABLES");
        //commit transaction
        $this->db->commit();
        return Result::success();
    }

    private function rollback() {
        $this->db->rollBack();
        //if not sqlite unlock tables
        if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== "sqlite") {
            $this->db->exec("UNLOCK TABLES");
        }
    }

}
