<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$logfile = __DIR__ . '/../log/info.log';
file_put_contents($logfile, '');

$log = new Logger('GrowthForecast');
$log->pushHandler(new StreamHandler($logfile, Logger::INFO));


$main = new \Matsubo\GrowthForecast\Client\MacOS('host', 'teraren');
$main->setLogger($log);
$main->setDevices(
    array(
        '/dev/disk2',
        '/dev/disk3s2',
        '/dev/disk4s2',
    )
);
$main->execute();



