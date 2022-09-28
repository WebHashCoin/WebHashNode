<?php

namespace WebHash\Network\Miner;

use WebHash\Helper\Serializer;
use WebHash\Network\Cryptography\Algorithms\AbstractAlgorithm;
use WebHash\Network\Cryptography\Algorithms\AlgorithmArgon2id;
use WebHash\Network\Cryptography\Base58;
use WebHash\Network\Cryptography\SHA3;
use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\dao\MiningInfo;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Database\DatabaseConnection;
use WebHash\Network\Database\entities\BlockEntity;
use WebHash\Network\Database\entities\MiningInfoEntity;
use WebHash\Network\Functions\BlockFunctions;
use WebHash\Network\Functions\WalletFunctions;
use WebHash\WebHash;

class WebHashMiner
{

    private string $privateKey;
    private string $publicKey;


    private DatabaseConnection $databaseConnection;
    private BlockEntity $blockEntity;
    private MiningInfoEntity $miningInfoEntity;

    private int $lowestDeadline = PHP_INT_MAX;

    private bool $refreshInfos = true;
    private int $checkUpToDate = 0;
    private int $REFRESH_RATE = 5;

    private string $nonce = '';
    private int $triesPerNonce = 0;
    private int $maxTriesPerNonce = 50;

    private ?MiningInfo $miningInfo = null;
    private ?Block $block = null;

    private bool $externalMining = false;
    private string $externalMiningUrl = '';

    private AbstractAlgorithm $algorithm;


    public function __construct($privateKey = '', $publicKey = '',$externalMining = false)
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->algorithm = new AlgorithmArgon2id();
        if(!$externalMining) {
            $this->databaseConnection = WebHash::getDatabaseConnection();
            $this->blockEntity = new BlockEntity($this->databaseConnection);
            $this->miningInfoEntity = new MiningInfoEntity($this->databaseConnection);
        }
        $this->externalMining = $externalMining;
        if($this->checkUpToDate == 0) {
            $this->checkUpToDate = time();
        }
    }

    public function setExternalMiningUrl(string $url) {
        $this->externalMiningUrl = $url;
    }

    public function startMining() {
        echo PHP_EOL. "Starting mining...".PHP_EOL;
        if($this->externalMining) {
            echo "External mining enabled".PHP_EOL;
            //check if the external mining url is valid
            if(!filter_var($this->externalMiningUrl, FILTER_VALIDATE_URL)) {
                echo "Invalid external mining url".PHP_EOL;
                return;
            }
        }
        while(true) {
            //generate new nonce
            $this->generateNonce();
            $this->checkBlock();
            echo "Mining block ".$this->block->getHeight()." with difficulty ".$this->miningInfo->getDifficulty()."...".PHP_EOL;
            $miningResult = $this->mine();
            $this->submitBlock($miningResult);
        }
    }

    private function updateInfo($miningInfo = null, ?Block $block = null) {
        if(!$miningInfo) {
            $this->miningInfo = $this->miningInfoEntity->getLatestMiningInfo();
        } else {
            $this->miningInfo = $miningInfo;
        }
        if(!$block) {
            $this->block = $this->blockEntity->getLatestBlock();
        } else {
            $this->block = $block;
        }
        $this->block->setTransactions($this->miningInfo->getUnconfirmedTransactionsCount());
    }

    public function mineBlock(Block $block, MiningInfo $miningInfo) : MiningResult
    {
        $this->generateNonce();
        $this->block = $block;
        $this->miningInfo = $miningInfo;
        echo PHP_EOL."Directly Mining ".$block->getHeight()."...".PHP_EOL;
        return $this->mine();
    }

    private function generateNonce() {
        //the nonce is a random string of 16 alphanumeric characters
        $this->nonce = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        for ($i = 0; $i < 16; $i++) {
            $this->nonce .= $characters[rand(0, $charactersLength - 1)];
        }
    }

    private function mine() : MiningResult
    {
        //check if the block is up-to-date
        if ($this->refreshInfos && $this->checkUpToDate + $this->REFRESH_RATE  < time()) {
            $this->checkUpToDate = time();
            $this->checkBlock();
        }
        $this->triesPerNonce++;
        if($this->triesPerNonce > $this->maxTriesPerNonce) {
            $this->triesPerNonce = 0;
            $this->generateNonce();
        }
        //generate random nonce
        $previousHash = $this->block->getId();
        $timestamp = time();
        $difficulty = $this->miningInfo->getDifficulty();
        //generate argon hash
        $algorithmHash = $this->algorithm->hash($this->nonce);
        $hash = $algorithmHash;
        //hash 6 times
        try {
            for ($i = 0; $i < 6; $i++) {
                $hash = SHA3::hash($hash,512);
            }
        } catch (\Exception $e) {
            $hash = "";
            error_log($e->getMessage());
            echo PHP_EOL;
        }
        if($hash === "") {
            return $this->mine();
        }
        //returns 64 parts with 2 characters each
        $hashParts = str_split($hash, 2);
        // calculate a number from the parts of the hash, each number has 2 characters
        //select 8 numbers from the places 4,6,15,20,31,43,56,61
        //get the numbers using hexdec
        $hashNumber =
            hexdec($hashParts[4]).
            hexdec($hashParts[6]).
            hexdec($hashParts[15]).
            hexdec($hashParts[20]).
            hexdec($hashParts[31]).
            hexdec($hashParts[43]).
            hexdec($hashParts[56]).
            hexdec($hashParts[61]);
        //remove leading zeros
        $hashNumber = ltrim($hashNumber, '0');
        //divide the number by the difficulty
        $result = gmp_div($hashNumber, $difficulty);
        //create the deadline as a float
        $deadline = gmp_strval($result);

        if($this->lowestDeadline > $deadline) {
            $this->lowestDeadline = $deadline;
            $this->triesPerNonce = 0;

            //Log the lowest deadline and remove old using ANSI escape codes
        echo "Current Lowest Deadline: ".str_pad("$this->lowestDeadline", 20, ' ')." \r";
        }

        //check if deadline is valid
        if($deadline > WebHash::$mining_deadline || $deadline <= 0) {
            return $this->mine();
        }

        $this->lowestDeadline = PHP_INT_MAX;
        echo PHP_EOL;

        echo ("==============================");
        echo PHP_EOL;
        echo ("Nonce: ".$this->nonce);
        echo PHP_EOL;
        echo ("Submitting Block");
        echo PHP_EOL;
        //create new block
        $block = new Block();
        $block->setId($hash);
        $block->setPreviousBlockId($previousHash);
        $block->setNonce($this->nonce);
        $block->setTimestamp($timestamp);
        $block->setTransactions($this->miningInfo->getUnconfirmedTransactionsCount());
        $block->setTransactionsHash($this->miningInfo->getUnconfirmedTransactionsHash());
        $block->setGenerator($this->publicKey);
        $block->setDifficulty($difficulty);
        $block->setHeight($this->block->getHeight() + 1);
        $block->setAlgorithmHash($algorithmHash);

        //create reward transaction
        $rewardTransaction = new Transaction();
        $rewardTransaction->setSender($this->publicKey);
        $rewardTransaction->setRecipient(WalletFunctions::generateAddress($this->publicKey));
        $rewardTransaction->setAmount($this->miningInfo->getReward());
        $rewardTransaction->setFee(0);
        $rewardTransaction->setTimestamp($timestamp);
        $rewardTransaction->setNonce($this->nonce);
        $rewardTransaction->setBlockId($hash);
        $rewardTransaction->setType(Transaction::TYPE_REWARD);
        //hash reward transaction
        $data = $rewardTransaction->getTransactionData();
        try {
            $rewardTransaction->setId(SHA3::hash($data, 256));
        } catch (\Exception $e) {
            error_log($e->getMessage());
            echo PHP_EOL;
        }
        //sign reward transaction
        $rewardTransaction->setSignature(Base58::encode(WalletFunctions::sign($data, $this->privateKey)));
        //add reward transaction to block
        $block->setRewardTransactionId($rewardTransaction->getId());

        //sign block
        $signatureData = $block->getSignatureData();
        $signatureData .= $this->miningInfo->getUnconfirmedTransactionsHash();
        $signature = WalletFunctions::sign($signatureData, $this->privateKey);
        $block->setSignature(Base58::encode($signature));

        $miningResult = new MiningResult();
        $miningResult->setBlock($block);
        $miningResult->setRewardTransaction($rewardTransaction);
        $miningResult->setMiningInfo($this->miningInfo);
        return $miningResult;
    }

    private function checkBlock()
    {
        if(!$this->externalMining) {
            $miningInfo = $this->miningInfoEntity->getLatestMiningInfo();
            $block = null;
        } else {
            //curl to external miner
            $ch = curl_init();
            $infoUrl = $this->externalMiningUrl . "/mine/info";
            curl_setopt($ch, CURLOPT_URL, $infoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            //data returned should look like: ['block'=>blockData, 'miningInfo'=>miningInfoData]
            $data = json_decode($result);
            //check if json is valid
            if($data === null) {
                echo "Invalid JSON returned from external miner" . PHP_EOL;
                return;
            }
            $data = $data->data;
            $blockData = $data->block;
            $miningInfoData = $data->miningInfo;
            //check if data is set, not null and valid
            if(!isset($blockData) || $blockData == null) {
                error_log("Block data is not set or null");
                return;
            }
            if(!isset($miningInfoData) || $miningInfoData == null) {
                error_log("Mining info data is not set or null");
                return;
            }
            //create block class from blockData
            $block = Serializer::serializeToClass($blockData, Block::class);
            //create miningInfo class from miningInfoData
            $miningInfo = Serializer::serializeToClass($miningInfoData, MiningInfo::class);
        }

        //check if block is outdated
        $isBlockSet = isset($this->block) || $this->block !== null;
        if(!$isBlockSet || $miningInfo->getBlockId() != $this->block->getId()) {
            $this->updateInfo($miningInfo, $block);
            $this->generateNonce();
            $this->lowestDeadline = PHP_INT_MAX;
            echo "Block outdated, mining new block" . PHP_EOL;
        }
    }

    private function submitBlock(MiningResult $miningResult) {

        if(!$this->externalMining) {
            $success = $this->blockEntity->addBlock($miningResult->getBlock(), $miningResult->getRewardTransaction());
            if($success) {
                echo "Block mined successfully " . $miningResult->getBlock()->getId() . PHP_EOL;
                echo "Got reward of: " . $miningResult->getMiningInfo()->getReward() . PHP_EOL;
            } else {
                echo "Invalid block. Trying again". PHP_EOL;
            }
        } else {
            //submit block to external miner using curl and POST data
            $ch = curl_init();
            $submitUrl = $this->externalMiningUrl . "/mine/submit";
            curl_setopt($ch, CURLOPT_URL, $submitUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            //data should look like: ["block"=>blockdata, "transaction"=>rewardTransactionData]
            $data = [
                "block" => $miningResult->getBlock(),
                "transaction" => $miningResult->getRewardTransaction()
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            //result should look like: ["success"=>true, "message"=>"message"]
            echo "OUTPUT: $result". PHP_EOL;
            $result = json_decode($result);
            //check status is == 1
            if($result->status != 1) {
                echo "Invalid block. Trying again". PHP_EOL;
                return;
            } else {
                echo "Block mined successfully " . $miningResult->getBlock()->getId() . PHP_EOL;
                echo "Got reward of: " . $miningResult->getMiningInfo()->getReward() . PHP_EOL;
            }
        }
    }

    /**
     * @param bool $refreshInfos
     */
    public function setRefreshInfos(bool $refreshInfos): void
    {
        $this->refreshInfos = $refreshInfos;
    }
}
