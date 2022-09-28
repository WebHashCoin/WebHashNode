<?php

namespace WebHash\Network\Database\DAO;

use JsonSerializable;

class Transaction implements JsonSerializable
{
    private string $id;
    private string $nonce;
    private string $sender;
    private string $recipient;
    private int $type = self::TYPE_PAYMENT;
    private float $amount;
    private float $fee;
    private string $signature;
    private int $timestamp;
    private ?string $block_id = null;

    public CONST TYPE_PAYMENT = 0;
    public CONST TYPE_REWARD = 1;

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
     * @return string
     */
    public function getSender(): string
    {
        return $this->sender;
    }

    /**
     * @param string $sender
     */
    public function setSender(string $sender): void
    {
        $this->sender = $sender;
    }

    /**
     * @return string
     */
    public function getRecipient(): string
    {
        return $this->recipient;
    }

    /**
     * @param string $recipient
     */
    public function setRecipient(string $recipient): void
    {
        $this->recipient = $recipient;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return float
     */
    public function getFee(): float
    {
        return $this->fee;
    }

    /**
     * @param float $fee
     */
    public function setFee(float $fee): void
    {
        $this->fee = $fee;
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
    public function getBlockId(): ?string
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
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }


    public function getTransactionData() : string
    {
        return $this->getSender() . $this->getRecipient() . $this->getAmount() . $this->getFee() . $this->getTimestamp() . $this->getNonce();
    }


    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
