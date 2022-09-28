<?php

use WebHash\Network\Database\entities\BlockEntity;
use WebHash\WebHash;

require_once __DIR__ . '/../vendor/autoload.php';

//argument 1 is the height of the block to delete
$height = $argv[1] ?? 2;

$webHash = new WebHash();
$webHash->init();

$blockEntity = new BlockEntity(WebHash::getDatabaseConnection());
$block = $blockEntity->deleteBlockAtHeight($height);
