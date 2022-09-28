<?php

namespace WebHash\Network\Functions;

use Error;
use WebHash\Network\Cryptography\Base58;
use WebHash\Network\Cryptography\SHA3;
use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\dao\MiningInfo;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Database\entities\BlockEntity;
use WebHash\Network\Database\entities\TransactionEntity;
use WebHash\Network\Miner\WebHashMiner;
use WebHash\WebHash;

class BlockFunctions
{
    public static function generateGenesisBlock() : ?Block
    {
        //generate the genesis wallet
        $genesisWallet = WalletFunctions::generateWallet();
        //generate the genesis block by starting to mine it
        error_log("Generating genesis block...");
        $block = new Block();
        $block->setHeight(0);
        $block->setId('GENESIS');
        $miningInfo = new MiningInfo();
        $miningInfo->setDifficulty(WebHash::$mining_starting_difficulty);
        $miningInfo->setBlockId('GENESIS');
        $miningInfo->setUnconfirmedTransactionsCount(0);
        $miningInfo->setUnconfirmedTransactionsHash(BlockFunctions::getBlockTransactionsHash(null,true));
        $miningInfo->setReward(0);
        $miner = new WebHashMiner($genesisWallet['privateKey'], $genesisWallet['publicKey']);
        $miner->setRefreshInfos(false);
        $miningResult = $miner->mineBlock($block, $miningInfo);
        //add block to database
        $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
        $blockEntity->addBlock($miningResult->getBlock(), $miningResult->getRewardTransaction());
        return null;
    }


    public static function calculateDifficulty(int $height): int
    {

        //check the last 10 blocks
        $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
        $blocks = $blockEntity->getBlocks(10, $height);
        $averageTime = 0;
        $averageDifficulty = 0;
        $lastBlock = null;
        foreach ($blocks as $block) {
            if ($lastBlock !== null) {
                $averageTime += abs( $lastBlock->getTimestamp() - $block->getTimestamp());
                $averageDifficulty += $lastBlock->getDifficulty();
            }
            $lastBlock = $block;
        }
        $numberOfBlocks = count($blocks);
        if($numberOfBlocks > 0) {
            $averageTime = $averageTime / $numberOfBlocks;
            $averageDifficulty = $averageDifficulty / $numberOfBlocks;
        }
        //formula to calculate the difficulty to reach 60 seconds per block
        $timeToReach = WebHash::$mining_target_blocktime;
        $percentage = $averageTime / $timeToReach;
        //if percentage increase would increase the difficulty too much, set it to the maximum
        $isPositiveIncrease = $percentage > 1;
        $difficultyToReach = $averageDifficulty * $percentage;
        if($isPositiveIncrease && $difficultyToReach >= PHP_INT_MAX || $difficultyToReach < 0) {
            //reset difficulty
            $difficultyToReach = WebHash::$mining_starting_difficulty;
        } else if(!$isPositiveIncrease && $difficultyToReach <= 0) {
            //reset difficulty
            $difficultyToReach = WebHash::$mining_starting_difficulty;
        }
        //error log
        error_log("Average Time: ".$averageTime);
        error_log("New difficulty: ".$difficultyToReach);
        //round to integer
        $difficulty = (int) round($difficultyToReach);
        //check if difficulty is too low
        if ($difficulty <= 0) {
            $difficulty = WebHash::$mining_starting_difficulty;
        }
        if($difficulty > PHP_INT_MAX) {
            $difficulty = PHP_INT_MAX;
        }
        return $difficulty;
    }

    public static function getBlockTransactionsHash(?Block $block, $unconfirmed = false) : string
    {
        $transactionEntity = new TransactionEntity(WebHash::getDatabaseConnection());
        if($unconfirmed) {
            $transactions = $transactionEntity->getUnconfirmedTransactions();
        } else {
            $transactions = $transactionEntity->getTransactionsByBlockId($block->getId());
            foreach ($transactions as $key => $transaction) {
                if($transaction instanceof Transaction) {
                    if($transaction->getType() === Transaction::TYPE_REWARD) {
                        unset($transactions[$key]);
                    }
                }
            }
        }
        return self::createTransactionsHash($transactions);
    }

    public static function createTransactionsHash(array $transactions) : string {
        $hash = '';
        foreach($transactions as $transaction) {
            if($transaction instanceof Transaction) {
                $hash .= $transaction->getId();
            }
        }
        try {
            return SHA3::hash($hash, 256);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return '';
        }
    }

    public static function verifyBlock(Block $block,bool $unconfirmed = false,array $transactionList = null) : bool
    {
        $blockId = $block->getId();
        $algorithm = $block->getAlgorithm();
        $algorithmHash = $block->getAlgorithmHash();
        $algorithm = WebHash::getAlgorithm($algorithm);
        if($algorithm === null) {
            error_log("Invalid algorithm");
            return false;
        }
        if(!$algorithm->verify($block->getNonce(),$algorithmHash)) {
            error_log("Invalid block hash");
            return false;
        }
        $blockData = $block->getSignatureData();
        if($transactionList === null) {
            $blockData .= self::getBlockTransactionsHash($block, $unconfirmed);
        } else {
            self:self::createTransactionsHash($transactionList);
        }
        $publicKeyGenerator = $block->getGenerator();
        $signature = Base58::decode($block->getSignature());
        if(!WalletFunctions::verify($blockData, $signature, $publicKeyGenerator)) {
            error_log("Invalid block signature");
            return false;
        }
        //check block id by hashing the algorithm hash 6 times
        $hash = $algorithmHash;
        try {
            for ($i = 0; $i < 6; $i++) {
                $hash = SHA3::hash($hash,512);
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
            echo PHP_EOL;
        }
        if($hash !== $blockId) {
            error_log("Invalid block id");
            return false;
        }
        return true;
    }

    public static function calculateReward(Block $block) : float
    {
        //get the block height
        $height = $block->getHeight();
        //calculate the reward by an elliptic curve function
        $startingReward = (float) WebHash::$mining_reward_max;
        $halvingAt = (float) WebHash::$mining_reward_halving;
        return $startingReward * pow(0.5, floor($height / $halvingAt));
    }

}
