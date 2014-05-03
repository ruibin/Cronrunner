<?php
require '../common.php';
$_SC['logfile_pre'] = "test_error_handler";
//trigger_error("this is a test", E_USER_NOTICE);

//throw new Exception("test");
echo 2/0;