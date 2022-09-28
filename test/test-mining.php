<?php
//autoloader and initialization for controller endpoints
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . './test-wallet.php';

use WebHash\Network\miner\WebHashMiner;
use WebHash\WebHash;

$webHash = new WebHash();
$webHash->init();

$miner = new WebHashMiner(PRIVATE_KEY, PUBLIC_KEY , true);
$miner->setExternalMiningUrl('http://localhost:8083');
$miner->startMining();
