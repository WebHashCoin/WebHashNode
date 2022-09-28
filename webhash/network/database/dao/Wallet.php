<?php

namespace WebHash\Network\Database\DAO;

use JsonSerializable;

class Wallet implements JsonSerializable
{
    private string $address;
    private ?string $public_key;
    private float $balance;
    private string $block_id;
    private int $blocks_mined;

    /**
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @param string $address
     */
    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    /**
     * @return float
     */
    public function getBalance(): float
    {
        return $this->balance;
    }

    /**
     * @param float $balance
     */
    public function setBalance(float $balance): void
    {
        $this->balance = $balance;
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

    /**
     * @return int
     */
    public function getBlocksMined(): int
    {
        return $this->blocks_mined;
    }

    /**
     * @param int $blocks_mined
     */
    public function setBlocksMined(int $blocks_mined): void
    {
        $this->blocks_mined = $blocks_mined;
    }

    /**
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->public_key;
    }

    /**
     * @param string|null $public_key
     */
    public function setPublicKey(?string $public_key): void
    {
        $this->public_key = $public_key;
    }


    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
