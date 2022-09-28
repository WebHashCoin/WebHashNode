<?php

namespace WebHash\Network\Database\DAO;

use JsonSerializable;

class Node implements JsonSerializable
{
    protected string $id;
    protected int $status;
    protected string $name;
    protected string $url;
    protected string $publicKey;
    protected string $protocol;
    protected string $version;
    protected string $blockHash;
    protected int $blockHeight;
    protected int $connectionCount;
    protected int $peersCount;
    protected int $transactionsCount;
    protected int $unconfirmedTransactionsCount;
    protected int $introducedAt;
    protected int $lastSeen;


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
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @param string $publicKey
     */
    public function setPublicKey(string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }

    /**
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * @param string $protocol
     */
    public function setProtocol(string $protocol): void
    {
        $this->protocol = $protocol;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getBlockHash(): string
    {
        return $this->blockHash;
    }

    /**
     * @param string $blockHash
     */
    public function setBlockHash(string $blockHash): void
    {
        $this->blockHash = $blockHash;
    }

    /**
     * @return int
     */
    public function getBlockHeight(): int
    {
        return $this->blockHeight;
    }

    /**
     * @param int $blockHeight
     */
    public function setBlockHeight(int $blockHeight): void
    {
        $this->blockHeight = $blockHeight;
    }

    /**
     * @return int
     */
    public function getConnectionCount(): int
    {
        return $this->connectionCount;
    }

    /**
     * @param int $connectionCount
     */
    public function setConnectionCount(int $connectionCount): void
    {
        $this->connectionCount = $connectionCount;
    }

    /**
     * @return int
     */
    public function getPeersCount(): int
    {
        return $this->peersCount;
    }

    /**
     * @param int $peersCount
     */
    public function setPeersCount(int $peersCount): void
    {
        $this->peersCount = $peersCount;
    }

    /**
     * @return int
     */
    public function getTransactionsCount(): int
    {
        return $this->transactionsCount;
    }

    /**
     * @param int $transactionsCount
     */
    public function setTransactionsCount(int $transactionsCount): void
    {
        $this->transactionsCount = $transactionsCount;
    }

    /**
     * @return int
     */
    public function getUnconfirmedTransactionsCount(): int
    {
        return $this->unconfirmedTransactionsCount;
    }

    /**
     * @param int $unconfirmedTransactionsCount
     */
    public function setUnconfirmedTransactionsCount(int $unconfirmedTransactionsCount): void
    {
        $this->unconfirmedTransactionsCount = $unconfirmedTransactionsCount;
    }

    /**
     * @return int
     */
    public function getIntroducedAt(): int
    {
        return $this->introducedAt;
    }

    /**
     * @param int $introducedAt
     */
    public function setIntroducedAt(int $introducedAt): void
    {
        $this->introducedAt = $introducedAt;
    }

    /**
     * @return int
     */
    public function getLastSeen(): int
    {
        return $this->lastSeen;
    }

    /**
     * @param int $lastSeen
     */
    public function setLastSeen(int $lastSeen): void
    {
        $this->lastSeen = $lastSeen;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
