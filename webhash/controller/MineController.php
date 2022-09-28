<?php

namespace WebHash\Controller;

use WebHash\Network\Database\entities\BlockEntity;
use WebHash\Network\Database\entities\MiningInfoEntity;
use WebHash\Network\Database\entities\TransactionEntity;
use WebHash\Network\Functions\BlockFunctions;
use WebHash\Helper\Result;
use WebHash\WebHash;
use WebHash\Annotation\Route;
use WebHash\Helper\Serializer;
use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Functions\TransactionFunctions;

class MineController
{


    /**
     * @Route(route="/mine/info", method="GET")
     * @return Result
     */
    public function mineInfoController() : Result
    {
        $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
        $miningInfoEntity = new MiningInfoEntity(WebHash::getDatabaseConnection());
        $block = $blockEntity->getLatestBlock();
        $miningInfo = $miningInfoEntity->getLatestMiningInfo();
        if($block === null || $miningInfo === null) {
            return Result::error("No block or mining info found");
        }
        $data = [
            'block' => $block,
            'miningInfo' => $miningInfo
        ];
        return Result::success($data);
    }

    /**
     * @Route(route="/mine/submit", method="POST", params={"block", "transaction"})
     * @return Result
     */
    public function mineSubmitController() : Result
    {
        //await content-type: application/json from request and $_POST
        $data = json_decode(file_get_contents('php://input'));
        $blockData = $data->block;
        $transactionData = $data->transaction;
        //serialize blockData to Block
        $block = Serializer::serializeToClass($blockData, Block::class);
        //serialize transactionData to Transaction
        $transaction = Serializer::serializeToClass($transactionData, Transaction::class);
        //check instance of block and transaction
        if(!($block instanceof Block) || !($transaction instanceof Transaction)) {
            return Result::error("Invalid data! Could not serialize block or transaction");
        }

        $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
        $result = $blockEntity->addBlock($block, $transaction);
        if(!$result->isSuccessful()) {
            return $result;
        }
        return Result::success($block->getId(), "Block added to blockchain");
    }

}
