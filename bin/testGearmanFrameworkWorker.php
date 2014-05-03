<?php
require '../common.php';
$app = CApp::create($appRoot, $sysConfig);
// job name
$app->init();
$cmd_param = $argv;
$gearmanHandler = $app->TestGearmanWorker;
if (!isset($cmd_param[1]) || $cmd_param[1] == "start") {
    $gearmanHandler->gearmanFork();
} else if ($cmd_param[1] == "restart"){
    $gearmanHandler->stopAllProcess();
    $gearmanHandler->gearmanFork();
} elseif ($cmd_param[1] == "stop"){
    $gearmanHandler->stopAllProcess();
}