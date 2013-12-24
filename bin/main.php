<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$log = new Logger('GrowthForecast');
$log->pushHandler(new StreamHandler(__DIR__ . '/../log/info.log', Logger::INFO));


$main = new \Matsubo\GrowthForecast\Client\MacOS('host', 'teraren');
$main->setLogger($log);
$main->execute();



