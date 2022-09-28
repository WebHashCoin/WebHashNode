<?php
namespace WebHash;

use WebHash\Network\Cryptography\algorithms\AbstractAlgorithm;
use WebHash\Network\Cryptography\algorithms\AlgorithmArgon2id;
use WebHash\Network\Database\DAO\Block;
use WebHash\Network\Database\DatabaseConnection;
use WebHash\Network\Database\entities\BlockEntity;
use WebHash\Network\Functions\BlockFunctions;

class WebHash
{
    public static string $network_version = "0.0.1";
    public static string $network_name = "WebHash";
    public static string $node_name = "WebHash Node";
    public static string $node_version = "0.0.1";

    //blocks
    public static string $block_algorithm = "argon2id";
    public static int $block_transaction_size = 100;
    //nodes
    public static int $peers_max = 100;
    //mining
    public static int $mining_deadline = 240;
    public static int $mining_reward_max = 100;
    public static int $mining_reward_halving = 210000;
    public static int $mining_target_blocktime = 10;
    public static int $mining_starting_difficulty = 1000000000000;

    private static DatabaseConnection $databaseConnection;
    private static array $algorithms = [];

    public function init()
    {
        //init database
        self::$databaseConnection = new DatabaseConnection();
        //init algorithms
        self::initAlgorithms();
        //init blockchain
        self::initBlockChain();
    }

    public function startRouting() {
        //the WebHash main controller sends the endpoints of other controllers (loaded in autoload.php) to the router
        $router = new Router();
        $result = $router->startRouting();
        $router->printResult($result);
    }

    private static function initAlgorithms()
    {
        $algorithms = glob(__DIR__ . "/network/cryptography/algorithms/Algorithm*.php");
        $namespace = "WebHash\\Network\\Cryptography\\Algorithms\\";
        $foundAlgorithms = array_map(function ($algorithm) use ($namespace) {
            return str_replace('.php', '', str_replace(__DIR__ . '/network/cryptography/algorithms/', $namespace, $algorithm));
        }, $algorithms);
        foreach ($foundAlgorithms as $algorithm) {
            self::$algorithms[] = new $algorithm();
        }
    }

    public static function getAlgorithm(string $algorithmName) : ?AbstractAlgorithm
    {
        foreach (self::$algorithms as $algorithm) {
            if ($algorithm->getName() == $algorithmName) {
                return $algorithm;
            }
        }
        return null;
    }

    private static function initBlockChain()
    {
        $blockEntity = new BlockEntity(self::$databaseConnection);
        $block = $blockEntity->getLatestBlock();
        if ($block === null) {
            BlockFunctions::generateGenesisBlock();
        }
    }

    /**
     * @return DatabaseConnection
     */
    public static function getDatabaseConnection(): DatabaseConnection
    {
        return self::$databaseConnection;
    }
}
