<?php
require '../common.php';

$_SC['logfile_pre'] = "test_autoload";
//$loader->lib('Logger');
//$fetch_tool = new DdFetchUrl();
//echo "auto load ok!\n";
$log_tool = new CLogger($_SC['log_on'], $_SC['log_level'], $_SC['log_in_shell'], $_SC['log_dir'], 
                       $_SC['logfile_pre'], $_SC['log_format_month'], $_SC['log_format_day']);
$log_tool->log("auto load logger ok!", "NOTICE");