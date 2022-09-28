<?php
//autoloader and initialization for controller endpoints
require_once 'vendor/autoload.php';
//load the WebHash main controller
use WebHash\WebHash;

//show errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


//this project is a lightweight framework for the WebHash - Node which is stored on MySQL and is used to store and retrieve data
//this is the main controller for the WebHash - Node
$webHash = new WebHash();
$webHash->init();
$webHash->startRouting();
