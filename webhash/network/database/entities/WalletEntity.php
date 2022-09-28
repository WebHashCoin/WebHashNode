<?php

namespace WebHash\Network\Database\entities;

use PDO;
use WebHash\Helper\Result;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Database\DAO\Wallet;
use WebHash\Network\Functions\TransactionFunctions;
use WebHash\Network\Functions\WalletFunctions;
use WebHash\WebHash;

class WalletEntity extends AbstractEntity
{

    /**
     * Returns the Wallet DAO from the database by address
     * if the wallet does not exist, null is returned
     *
     * @param string $address
     * @return Wallet|null
     */
    public function getWallet(string $address) : ?Wallet
    {
        $stmt = $this->db->prepare("SELECT * FROM `wallets` WHERE address = :address");
        $stmt->execute(['address' => $address]);
        //bind to Wallet class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Wallet::class);
        $wallet = $stmt->fetch();
        if($wallet instanceof Wallet) {
            return $wallet;
        } else {
            return null;
        }
    }

    /**
     * Returns the Wallet DAO from the database from publicKey
     * if the wallet does not exist, null is returned
     *
     * @param string $publicKey
     * @return Wallet|null
     */
    public function getWalletByPublicKey(string $publicKey) : ?Wallet
    {
        $stmt = $this->db->prepare("SELECT * FROM `wallets` WHERE public_key = :public_key");
        $stmt->execute(['public_key' => $publicKey]);
        //bind to Wallet class
        $stmt->setFetchMode(PDO::FETCH_CLASS, Wallet::class);
        $wallet = $stmt->fetch();
        if($wallet instanceof Wallet) {
            return $wallet;
        } else {
            return null;
        }
    }

    public function getBalance(string $address) : float
    {
        $stmt = $this->db->prepare("SELECT balance FROM `wallets` WHERE address = :address");
        $stmt->execute(['address' => $address]);
        return $stmt->fetchColumn();
    }

    public function updateAccountBalanceByTransaction(Transaction $transaction) : Result {
        $isReward = $transaction->getType() === Transaction::TYPE_REWARD;
        $transactionSenderPublicKey = $transaction->getSender();
        $transactionRecipient = $transaction->getRecipient();
        $transactionAmount = $transaction->getAmount();
        $transactionFee = $transaction->getFee();
        $transactionValid = TransactionFunctions::verifyTransaction($transaction);
        if(!$transactionValid->isSuccessful()) {
            return $transactionValid;
        }
        //transaction is valid and can be processed
        //if the recipient wallet is not in the database, create it
        if($this->getWallet($transactionRecipient) === null) {
            $state = $this->createWallet($transactionRecipient);
            if(!$state) {
                return Result::error("Failed to create wallet for recipient");
            }
        }
        //remove funds from sender if not reward
        if(!$isReward) {
            //generate address from public key
            $addressSender = WalletFunctions::generateAddress($transactionSenderPublicKey);
            $wallet = $this->getWallet($addressSender);
            if(!$wallet instanceof Wallet) {
                return Result::error("Failed to get wallet for sender");
            }
            //check if public_key is set
            if($wallet->getPublicKey() === null) {
                //update public key to new value
                $wallet->setPublicKey($transactionSenderPublicKey);
                if(!$this->updateWallet($wallet)) {
                    return Result::error("Failed to update wallet for sender (pub_key insert)");
                }
            }
            //update balance of sender
            $stmt = $this->db->prepare("UPDATE `wallets` SET balance = :balance WHERE address = :address");
            $transactionSenderBalance = $this->getBalance($addressSender);
            //remove the amount and fee of the transaction from the balance accurate to 8 decimal places
            $newBalance = round($transactionSenderBalance - $transactionAmount - $transactionFee, 8);
            $state = $stmt->execute(['balance' => $newBalance, 'address' => $addressSender]);
            if(!$state) {
                return Result::error("Failed to update wallet for sender");
            }
        }
        //add funds to recipient
        $stmt = $this->db->prepare("UPDATE `wallets` SET balance = :balance WHERE address = :address");
        $transactionRecipientBalance = $this->getBalance($transactionRecipient);
        $transactionRecipientBalance += $transactionAmount;
        $state = $stmt->execute(['balance' => $transactionRecipientBalance, 'address' => $transactionRecipient]);
        if(!$state) {
            return Result::error("Failed to update wallet for recipient");
        }
        //note that the fee is not added to the recipient
        $returnedData = [
            'recipient' => $transactionRecipient,
            'recipientBalance' => $transactionRecipientBalance
        ];
        //add sender if isset
        if(!$isReward) {
            $returnedData['sender'] = $addressSender;
            $returnedData['senderBalance'] = $transactionSenderBalance;
        }
        return Result::success($returnedData);
    }

    /**
     * This function is only used when the block which holds this transaction gets deleted.
     * It will remove the funds from the recipient and add them to the sender clearing the fees in the process.
     *
     * @param Transaction $transaction
     * @return Result
     */
    public function reverseAccountBalanceByTransaction(Transaction $transaction) : Result {
        $isReward = $transaction->getType() === Transaction::TYPE_REWARD;
        $transactionSenderPublicKey = $transaction->getSender();
        $transactionRecipient = $transaction->getRecipient();
        $transactionAmount = $transaction->getAmount();
        $transactionFee = $transaction->getFee();
        //remove funds from recipient
        $stmt = $this->db->prepare("UPDATE `wallets` SET balance = :balance WHERE address = :address");
        $transactionRecipientBalance = $this->getBalance($transactionRecipient);
        //remove the amount and fee of the transaction from the balance
        $transactionRecipientBalance -= $transactionAmount;
        $state = $stmt->execute(['balance' => $transactionRecipientBalance, 'address' => $transactionRecipient]);
        if(!$state) {
            return Result::error("Failed to update wallet for recipient");
        }
        //add funds to sender if the transaction is not a reward
        if(!$isReward) {
            //get wallet from sender, if it does not exist, throw error
            $wallet = $this->getWalletByPublicKey($transactionSenderPublicKey);
            if (!$wallet instanceof Wallet) {
                return Result::error("Failed to get wallet for sender");
            }
            $senderAddress = $wallet->getAddress();
            //update balance of sender
            $stmt = $this->db->prepare("UPDATE `wallets` SET balance = :balance WHERE address = :address");
            $transactionSenderBalance = $this->getBalance($senderAddress);
            //remove the amount and fee of the transaction from the balance
            $transactionSenderBalance += $transactionAmount + $transactionFee;
            $state = $stmt->execute(['balance' => $transactionSenderBalance, 'address' => $senderAddress]);
            if (!$state) {
                return Result::error("Failed to update wallet for sender");
            }
        }
        return Result::success();
    }

    private function createWallet($transactionRecipient) : bool
    {
        $stmt = $this->db->prepare("INSERT INTO `wallets` (address, balance, block_id, blocks_mined) VALUES (:address, :balance, :block_id, :blocks_mined)");
        //current block id
        $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
        $block = $blockEntity->getLatestBlock();
        if($block === null) {
            $blockId = "GENESIS";
        } else {
            $blockId = $block->getId();
        }
        return $stmt->execute(['address' => $transactionRecipient, 'balance' => 0, 'block_id' => $blockId, 'blocks_mined' => 0]);
    }

    public function updateWallet(Wallet $wallet) : bool
    {
      //check for changes that aren't the balance
        $stmt = $this->db->prepare("UPDATE `wallets` SET public_key = :public_key, block_id = :block_id, blocks_mined = :blocks_mined WHERE address = :address");
        return $stmt->execute(['public_key' => $wallet->getPublicKey(), 'block_id' => $wallet->getBlockId(), 'blocks_mined' => $wallet->getBlocksMined(), 'address' => $wallet->getAddress()]);
    }
}
