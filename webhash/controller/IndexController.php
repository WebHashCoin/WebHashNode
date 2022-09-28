<?php
namespace WebHash\Controller;

use WebHash\Annotation\Route;
use WebHash\Helper\Result;

class IndexController
{

    /**
     * @Route(route="/", method="GET")
     * @return Result
     */
    public function mainController() : Result
    {
        //load the public directory and show the index.html
        $publicDir = __DIR__ . "/../../public";
        $indexFile = $publicDir . "/index.html";
        if(file_exists($indexFile)) {
            $index = file_get_contents($indexFile);
            $result = new Result(200);
            $result->setData($index);
            $result->setContentType("text/html");
            $result->setCache(9600);
        } else {
            $result = new Result(404);
            $result->setMessage("Index file not found");
        }
        return $result;
    }

    /**
     * @Route(route="/assets/*", method="GET")
     * @return Result
     */
    public function assetsController() : Result
    {
        //load the public directory and show assets
        $publicDir = __DIR__ . "/../../public";
        $assetFile = $publicDir . $_SERVER['REQUEST_URI'];
        if(file_exists($assetFile) && is_file($assetFile)) {
            $asset = file_get_contents($assetFile);
            $result = new Result(200);
            $result->setData($asset);
            $result->setContentType("text/html");
        } else {
            $result = new Result(404);
            $result->setMessage("Asset file not found");
        }
        return $result;
    }
}
