<?php

namespace WebHash\Network\Database\DAO;

use JsonSerializable;

//the dao of the mysql table Blocks
class Block implements JsonSerializable
{
    protected string $id;
    protected int $height;
    protected string $nonce;
    protected int $timestamp;
    protected string $generator;
    protected int $difficulty;
    protected string $reward_transaction_id;
    protected int $transactions;
    protected string $transactions_hash;
    protected string $signature;
    protected string $algorithm = "argon2id";
    protected string $algorithm_hash;
    protected string $previous_block_id;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @param int $height
     */
    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    /**
     * @return string
     */
    public function getNonce(): string
    {
        return $this->nonce;
    }

    /**
     * @param string $nonce
     */
    public function setNonce(string $nonce): void
    {
        $this->nonce = $nonce;
    }

    /**
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @param int $timestamp
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return string
     */
    public function getGenerator(): string
    {
        return $this->generator;
    }

    /**
     * @param string $generator
     */
    public function setGenerator(string $generator): void
    {
        $this->generator = $generator;
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
     * @return int
     */
    public function getTransactions(): int
    {
        return $this->transactions;
    }

    /**
     * @param int $transactions
     */
    public function setTransactions(int $transactions): void
    {
        $this->transactions = $transactions;
    }

    /**
     * @return string
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     */
    public function setSignature(string $signature): void
    {
        $this->signature = $signature;
    }

    /**
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * @param string $algorithm
     */
    public function setAlgorithm(string $algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    /**
     * @return string
     */
    public function getAlgorithmHash(): string
    {
        return $this->algorithm_hash;
    }

    /**
     * @param string $algorithm_hash
     */
    public function setAlgorithmHash(string $algorithm_hash): void
    {
        $this->algorithm_hash = $algorithm_hash;
    }

    /**
     * @return string
     */
    public function getPreviousBlockId(): string
    {
        return $this->previous_block_id;
    }

    /**
     * @param string $previous_block_id
     */
    public function setPreviousBlockId(string $previous_block_id): void
    {
        $this->previous_block_id = $previous_block_id;
    }

    /**
     * @return string
     */
    public function getSignatureData() : string
    {
        return $this->id . $this->height . $this->nonce . $this->timestamp . $this->generator . $this->difficulty . $this->transactions .$this->reward_transaction_id . $this->algorithm . $this->algorithm_hash . $this->previous_block_id;
    }

    /**
     * @return string
     */
    public function getTransactionsHash(): string
    {
        return $this->transactions_hash;
    }

    /**
     * @param string $transactions_hash
     */
    public function setTransactionsHash(string $transactions_hash): void
    {
        $this->transactions_hash = $transactions_hash;
    }

    /**
     * @return string
     */
    public function getRewardTransactionId(): string
    {
        return $this->reward_transaction_id;
    }

    /**
     * @param string $reward_transaction_id
     */
    public function setRewardTransactionId(string $reward_transaction_id): void
    {
        $this->reward_transaction_id = $reward_transaction_id;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
