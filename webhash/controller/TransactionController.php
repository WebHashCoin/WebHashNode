<?php

namespace WebHash\Controller;
use WebHash\Annotation\Route;
use WebHash\Helper\Result;
use WebHash\Network\Database\DAO\Transaction;
use WebHash\Network\Database\entities\BlockEntity;
use WebHash\Network\Database\entities\TransactionEntity;
use WebHash\WebHash;

class TransactionController
{

    /**
     * @Route(route="/transaction/get/*", method="GET")
     * @return Result
     */
    public function getTransaction() : Result
    {
        //get search method from route
        //possible search methods: block, address, id
        //urls look like: /transaction/get/block/(id), /transaction/get/address/(id), /transaction/get/(id)

        $request_uri = $_SERVER['REQUEST_URI'];
        $request_uri = explode("/", $request_uri);
        //get text after  /transaction/get/
        $searchMethod = $request_uri[3];
        //get text after last separator
        $searchValue = end($request_uri);
        //if searchValue and searchMethod are the same search for id
        if($searchMethod === $searchValue) {
            $searchMethod = "id";
        }
        $transactionEntity = new TransactionEntity(WebHash::getDatabaseConnection());
        if($searchMethod === "block") {
            //verify if block exists
            $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
            $block = $blockEntity->getBlockById($searchValue);
            if($block === null) {
                return Result::error("Block not found");
            }
            $transactions = $transactionEntity->getTransactionsByBlockId($searchValue);
        } else if($searchMethod === "address") {
            $transactions = $transactionEntity->getTransactionsByAddress($searchValue);
        } else if($searchMethod === "id") {
            $transactions = $transactionEntity->getTransactionById($searchValue);
        } else {
            return Result::error("Invalid search method. Try block, address or id");
        }
        //single transaction, add confirmations
        if($transactions instanceof Transaction) {
            $confirmations = $transactionEntity->getConfirmations($transactions);
            $transactions = [
                "confirmations" => $confirmations,
                "transaction" => $transactions
            ];
        }
        //$transactions is null if no transactions were found
        if($transactions === null) {
            return Result::error("Transaction not found");
        } else {
            return Result::success($transactions);
        }
    }

    /**
     * @Route(route="/transaction/send", method="POST", params={"transaction"})
     * @return Result
     */
    public function sendTransaction() : Result
    {
        $data = json_decode(file_get_contents('php://input'));
        $transaction = $data->transaction;
        $transactionEntity = new TransactionEntity(WebHash::getDatabaseConnection());
        $result = $transactionEntity->addTransaction($transaction);
        if(!$result->isSuccessful()) {
            return $result;
        }
        return Result::success("Transaction added to pool");
    }

}
