<?php
require '../common.php';
//var_dump($appRoot);
//exit();
//require 'adfasdf.php';
$app = CApp::create($appRoot, $sysConfig);
$app->init('TestFramework');
$app->run();
