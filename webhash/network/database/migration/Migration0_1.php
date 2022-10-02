<?php

namespace WebHash\Network\Database\Migration;

use WebHash\Annotation\Migration;
use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\entities\BlockEntity;
use WebHash\WebHash;

class Migration0_1 extends AbstractMigration
{
    protected function upgrade()
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS `nodes` (
            `id` VARCHAR(255) PRIMARY KEY UNIQUE NOT NULL,
            `status` INTEGER NOT NULL,
            `url` VARCHAR(255) NOT NULL UNIQUE,
            `public_key` VARCHAR(255) UNIQUE,
            `protocol` VARCHAR(15) NOT NULL,
            `version` VARCHAR(15),
            `block_hash` VARCHAR(255),
            `block_height` INTEGER DEFAULT 0,
            `connection_count` INTEGER DEFAULT 0,
            `peers_count` INTEGER DEFAULT 0,
            `transactions_count` INTEGER DEFAULT 0,
            `unconfirmed_transactions_count` INTEGER DEFAULT 0,
            `introduced_at` INTEGER DEFAULT (strftime(\'%s\', \'now\')) NOT NULL,
            `last_seen` INTEGER
        )');

        $this->db->exec('CREATE TABLE IF NOT EXISTS `blocks` (
            `id` VARCHAR(255) PRIMARY KEY,
            `height` INTEGER NOT NULL UNIQUE,
            `nonce` VARCHAR(16) NOT NULL,
            `timestamp` INTEGER NOT NULL,
            `generator` VARCHAR(255) NOT NULL,
            `difficulty` INTEGER NOT NULL,
            `reward_transaction_id` VARCHAR(255) NOT NULL,
            `transactions` INTEGER NOT NULL,
            `transactions_hash` VARCHAR(255) NOT NULL,
            `signature` VARCHAR(255) NOT NULL,
            `algorithm` VARCHAR(255) NOT NULL,
            `algorithm_hash` VARCHAR(255) NOT NULL,
            `previous_block_id` VARCHAR(255) NOT NULL REFERENCES `blocks`(`id`) ON DELETE CASCADE
        )');

        $this->db->exec('CREATE TABLE IF NOT EXISTS `transactions` (
            `id` VARCHAR(255) PRIMARY KEY,
            `nonce` VARCHAR(16) NOT NULL,
            `sender` VARCHAR(255) NOT NULL,
            `recipient` VARCHAR(255) NOT NULL,
            `type` INTEGER NOT NULL,
            `amount` decimal(20,8) NOT NULL,
            `fee` decimal(20,8) NOT NULL,
            `signature` VARCHAR(255) NOT NULL,
            `timestamp` INTEGER NOT NULL,
            `block_id` VARCHAR(255) REFERENCES `blocks`(`id`) ON DELETE CASCADE
        )');

        $this->db->exec('CREATE TABLE IF NOT EXISTS `wallets` (
            `address` VARCHAR(255) NOT NULL UNIQUE,
            `public_key` VARCHAR(255) UNIQUE,
            `balance` decimal(20,8) NOT NULL,
            `block_id` VARCHAR(255) NOT NULL REFERENCES `blocks`(`id`) ON DELETE CASCADE,
            `blocks_mined` INTEGER NOT NULL
        )');

        //create trigger after block delete to move unconfirmed_transactions to waiting_transactions
        $this->db->exec('CREATE TRIGGER IF NOT EXISTS `move_unconfirmed_transactions` AFTER DELETE ON `blocks` FOR EACH ROW BEGIN
            INSERT INTO `waiting_transactions` SELECT * FROM `unconfirmed_transactions`;
            DELETE FROM `unconfirmed_transactions`;
        END');

        //unconfirmed_transactions: copy column names from transaction table
        $this->db->exec('CREATE TABLE IF NOT EXISTS `unconfirmed_transactions` AS SELECT * FROM `transactions` WHERE 0');
        $this->db->exec('CREATE TABLE IF NOT EXISTS `waiting_transactions` AS SELECT * FROM `transactions` WHERE 0');

        //mining info
        $this->db->exec('CREATE TABLE IF NOT EXISTS `mining_info` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `block_id` VARCHAR(255) NOT NULL REFERENCES `blocks`(`id`) ON DELETE CASCADE UNIQUE,
            `unconfirmed_transactions_count` int NOT NULL,
            `unconfirmed_transactions_hash` VARCHAR(255) NOT NULL,
            `difficulty` int NOT NULL,
            `reward` decimal(20,8) NOT NULL
        )');
    }

    protected function downgrade()
    {
        $this->db->exec('DROP TABLE IF EXISTS `node`');
        $this->db->exec('DROP TABLE IF EXISTS `transactions`');
        $this->db->exec('DROP TABLE IF EXISTS `block`');
        $this->db->exec('DROP TABLE IF EXISTS `wallet`');
        $this->db->exec('DROP TABLE IF EXISTS `unconfirmed_transactions`');
        $this->db->exec('DROP TABLE IF EXISTS `waiting_transactions`');
        $this->db->exec('DROP TABLE IF EXISTS `mining_info`');
    }

    public function version(): int
    {
        return 1;
    }
}
