<?php
require '../common.php';
$app = CApp::create($appRoot, $sysConfig);
$app->init();
$app->TestGearmanClient->run();