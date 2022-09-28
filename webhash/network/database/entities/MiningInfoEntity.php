<?php

namespace WebHash\Network\Database\entities;

use PDO;
use WebHash\Network\Database\dao\MiningInfo;

class MiningInfoEntity extends AbstractEntity
{
    public function getMiningInfoOfBlock(string $blockId) : MiningInfo
    {
        $stmt = $this->db->prepare("SELECT * FROM `mining_info` WHERE block_id = :block_id");
        $stmt->execute(['block_id' => $blockId]);
        //bind to MiningInfo class
        $stmt->setFetchMode(PDO::FETCH_CLASS, MiningInfo::class);
        return $stmt->fetch();
    }

    public function getLatestMiningInfo() : ?MiningInfo
    {
        $stmt = $this->db->prepare("SELECT * FROM `mining_info` ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        //bind to MiningInfo class
        $stmt->setFetchMode(PDO::FETCH_CLASS, MiningInfo::class);
        $miningInfo = $stmt->fetch();
        if($miningInfo instanceof MiningInfo) {
            return $miningInfo;
        } else {
            return null;
        }
    }

    public function addMiningInfo(MiningInfo $miningInfo) : bool
    {
        $stmt = $this->db->prepare("INSERT INTO `mining_info` (block_id, unconfirmed_transactions_count, unconfirmed_transactions_hash, difficulty, reward) VALUES (:block_id, :unconfirmed_transactions_count, :unconfirmed_transactions_hash, :difficulty, :reward)");
        return $stmt->execute([
            'block_id' => $miningInfo->getBlockId(),
            'unconfirmed_transactions_count' => $miningInfo->getUnconfirmedTransactionsCount(),
            'unconfirmed_transactions_hash' => $miningInfo->getUnconfirmedTransactionsHash(),
            'difficulty' => $miningInfo->getDifficulty(),
            'reward' => $miningInfo->getReward()
        ]);
    }
}
