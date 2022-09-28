<?php

namespace WebHash\Network\Database\dao;

use JsonSerializable;

class MiningInfo implements JsonSerializable
{
    private int $id;
    private string $block_id;
    private int $unconfirmed_transactions_count;
    private string $unconfirmed_transactions_hash;
    private int $difficulty;
    private float $reward;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getUnconfirmedTransactionsCount(): int
    {
        return $this->unconfirmed_transactions_count;
    }

    /**
     * @param int $unconfirmed_transactions_count
     */
    public function setUnconfirmedTransactionsCount(int $unconfirmed_transactions_count): void
    {
        $this->unconfirmed_transactions_count = $unconfirmed_transactions_count;
    }

    /**
     * @return string
     */
    public function getUnconfirmedTransactionsHash(): string
    {
        return $this->unconfirmed_transactions_hash;
    }

    /**
     * @param string $unconfirmed_transactions_hash
     */
    public function setUnconfirmedTransactionsHash(string $unconfirmed_transactions_hash): void
    {
        $this->unconfirmed_transactions_hash = $unconfirmed_transactions_hash;
    }

    /**
     * @return int
     */
    public function getDifficulty(): int
    {
        return $this->difficulty;
    }

    /**
     * @param int $difficulty
     */
    public function setDifficulty(int $difficulty): void
    {
        $this->difficulty = $difficulty;
    }

    /**
     * @return float
     */
    public function getReward(): float
    {
        return $this->reward;
    }

    /**
     * @param float $reward
     */
    public function setReward(float $reward): void
    {
        $this->reward = $reward;
    }

    /**
     * @return string
     */
    public function getBlockId(): string
    {
        return $this->block_id;
    }

    /**
     * @param string $block_id
     */
    public function setBlockId(string $block_id): void
    {
        $this->block_id = $block_id;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
