<?php
/**
 * 加载系统类
 */
$coreDir = dirname(__FILE__).DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."core"
          .DIRECTORY_SEPARATOR;
require $coreDir.DIRECTORY_SEPARATOR.'CAutoLoader.php';
spl_autoload_register(array('CAutoLoader', 'loadClass'));
$appRoot = dirname(__FILE__);
$sysConfig = require $appRoot.DIRECTORY_SEPARATOR.'config.php';
date_default_timezone_set('PRC');
ini_set("opcache.enable", 0);
