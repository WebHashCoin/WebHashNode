<?php

namespace WebHash\Network\Miner;

use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\dao\MiningInfo;
use WebHash\Network\Database\DAO\Transaction;

class MiningResult
{
    protected Block $block;
    protected MiningInfo $miningInfo;
    protected Transaction $rewardTransaction;

    /**
     * @return Block
     */
    public function getBlock(): Block
    {
        return $this->block;
    }

    /**
     * @param Block $block
     */
    public function setBlock(Block $block): void
    {
        $this->block = $block;
    }

    /**
     * @return MiningInfo
     */
    public function getMiningInfo(): MiningInfo
    {
        return $this->miningInfo;
    }

    /**
     * @param MiningInfo $miningInfo
     */
    public function setMiningInfo(MiningInfo $miningInfo): void
    {
        $this->miningInfo = $miningInfo;
    }

    /**
     * @return Transaction
     */
    public function getRewardTransaction(): Transaction
    {
        return $this->rewardTransaction;
    }

    /**
     * @param Transaction $rewardTransaction
     */
    public function setRewardTransaction(Transaction $rewardTransaction): void
    {
        $this->rewardTransaction = $rewardTransaction;
    }
}
