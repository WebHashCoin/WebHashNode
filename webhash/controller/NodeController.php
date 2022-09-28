<?php

namespace WebHash\Controller;

use WebHash\Helper\Serializer;
use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\DAO\Node;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Database\entities\BlockEntity;
use WebHash\Network\Database\entities\MiningInfoEntity;
use WebHash\Network\Database\entities\NodeEntity;
use WebHash\Network\Database\entities\TransactionEntity;
use WebHash\Helper\Result;
use WebHash\Network\Functions\BlockFunctions;
use WebHash\Network\Functions\TransactionFunctions;
use WebHash\WebHash;
use WebHash\Annotation\Route;

class NodeController
{

    /**
    * @Route(route="/node/info", method="GET")
    * @return Result
    */
    public function nodeInfoController() : Result
    {
        //send current node Info ( block_height, transactions_count, peers_count, etc. )
        $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
        $miningInfoEntity = new MiningInfoEntity(WebHash::getDatabaseConnection());
        $transactionEntity = new TransactionEntity(WebHash::getDatabaseConnection());
        $block = $blockEntity->getLatestBlock();
        $miningInfo = $miningInfoEntity->getLatestMiningInfo();
        $data = [
            'block' => $block,
            'miningInfo' => $miningInfo,
            'transactionCount' => $transactionEntity->getTransactionCount()
        ];
        return Result::success($data);
    }

    public function askToPeerController() : Result
    {
        $data = json_decode(file_get_contents('php://input'));
        if (!isset($data->url) || !isset($data->publicKey)) {
            return Result::error("Invalid data! No peer or route found");
        }
        $nodeEntity = new NodeEntity(WebHash::getDatabaseConnection());
        //check if maximum nodes are reached
        $nodeCount = $nodeEntity->getNodeCount();
        if($nodeCount >= WebHash::$peers_max) {
            return Result::error("Maximum nodes reached");
        }
        $url = $data->url;
        $publicKey = $data->publicKey;
        //get protocol and host from url
        $urlParts = parse_url($url);
        $protocol = $urlParts['scheme'];
        $url = $urlParts['host'];
        $node = $nodeEntity->getNodeByPublicKey($publicKey);
        if($node !== null) {
            return Result::error("Node already exists");
        }
        //check if url is already in database
        $node = $nodeEntity->getNodeByUrl($url);
        if($node !== null) {
            return Result::error("Node already exists");
        }
        //a peer asks for permission to join the network
        $node = new Node();
        $node->setUrl($url);
        $node->setPublicKey($publicKey);
        $node->setProtocol($protocol);
        $node->setIntroducedAt(time());
        $nodeEntity = new NodeEntity(WebHash::getDatabaseConnection());#
        $nodeEntity->addNode($node);
    }

    public function recommendPeersController() : Result
    {

    }

    public function getPeersController() : Result
    {

    }

    /**
     * @Route(route="/node/submitBlock", method="POST")
     * @return Result
     */
    public function submitBlockController() : Result
    {
        //the block should be submitted with unconfirmed transactions
        //check if block and transactions are set as Content-Type: application/json
        $data = json_decode(file_get_contents('php://input'));
        //check if block and transactions are set
        if (!isset($data->block) || !isset($data->transactions) || !isset($data->rewardTransaction)) {
            return Result::error("Invalid data! No block, transactions or rewardTransaction found");
        }
        $blockData = $data->block;
        $transactionsData = $data->transactions;
        $rewardTransactionData = $data->rewardTransaction;

        //serialize blockData to Block
        $block = Serializer::serializeToClass($blockData, Block::class);
        //the transactions should be serialized to an array of Transaction
        $transactions = [];
        foreach ($transactionsData as $transactionData) {
            $transaction = Serializer::serializeToClass($transactionData, Transaction::class);
            if($transaction instanceof Transaction) {
                $result = TransactionFunctions::verifyTransaction($transaction);
                if(!$result->isSuccessful()) {
                    return Result::error("Invalid transaction[".$transaction->getId()."]! Transaction verification failed. Block rejected");
                }
                $transactions[] = $transaction;
            } else {
                return Result::error("Invalid transaction! Transaction could not be serialized. Block rejected", $transactionData);
            }
        }
        //serialize rewardTransactionData to Transaction
        $rewardTransaction = Serializer::serializeToClass($rewardTransactionData, Transaction::class);
        //check instance of block and transaction
        if (!($block instanceof Block) ||
            (count($transactions) != 0 && !($transactions[0] instanceof Transaction)) ||
            !($rewardTransaction instanceof Transaction)) {
            return Result::error("Invalid data! Block, transactions or rewardTransaction could not be serialized");
        }
        $nodeEntity = new NodeEntity(WebHash::getDatabaseConnection());
        $result = $nodeEntity->submitBlock($block, $transactions, $rewardTransaction);

        if(!$result->isSuccessful()) {
            return Result::error("Block could not be submitted", $result->getData());
        }
        return Result::success("Block submitted successfully");
    }

    /**
     * @Route(route="/node/submitTransaction", method="POST")
     * @return Result
     */
    public function submitTransactionController() : Result
    {
        //check if transaction is set as Content-Type: application/json
        $data = json_decode(file_get_contents('php://input'));
        //check if transaction is set
        if (!isset($data->transaction)) {
            return Result::error("Invalid data! No transaction found");
        }
        $transactionData = $data->transaction;
        //serialize transactionData to Transaction
        $transaction = Serializer::serializeToClass($transactionData, Transaction::class);
        //check instance of transaction
        if (!($transaction instanceof Transaction)) {
            return Result::error("Invalid data! Transaction could not be serialized");
        }
        $nodeEntity = new NodeEntity(WebHash::getDatabaseConnection());
        $result = $nodeEntity->submitTransaction($transaction);
        if(!$result->isSuccessful()) {
            return Result::error("Transaction could not be submitted", $result->getData());
        }
        return Result::success("Transaction submitted successfully");
    }
}
