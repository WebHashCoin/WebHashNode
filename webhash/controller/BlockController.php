<?php

namespace WebHash\Controller;

use WebHash\Annotation\Route;
use WebHash\Network\Database\entities\BlockEntity;
use WebHash\Helper\Result;
use WebHash\WebHash;

class BlockController
{
    /**
     * @Route(route="/block/get/*", method="GET")
     * @return Result
     */
    public function getBlock() : Result
    {
        //get search method from route
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_uri = explode("/", $request_uri);
        //get text after last separator
        $searchMethod = end($request_uri);
        //if searchMethod is "latest" return the latest block
        $blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
        if($searchMethod === "latest") {
            $block = $blockEntity->getLatestBlock();
        } else {
            $block = $blockEntity->getBlockById($searchMethod);
        }
        //if block is null return -1
        if($block === null) {
            return Result::error("Block not found");
        } else {
            return Result::success($block);
        }
    }

}
