<?php
return array(
    'appName'           => 'index_so_com',
    'displayErrors'     => true,
    'errorReporting'    => E_ALL,
    'logBacktrace'      => true,
    'extensionSwitch'   => true,
    'needExtensions'    => array('pcntl'),
    'defaultLoggerConf' => array('logOn' => true, 'logLevel' => 4, 'logInShell' => true,
                                'logFormatDay' => true, 'logFormatMonth' => false),
    'memLimit'          => "1G",
    // 注册模块，这样配置会自加载到相应模块里
    'modules' => array(
        /*
         * @logger 级别由高到低：ERROR->NOTICE->WARNNING->EXCEPTION->INFO->ALL
         * 级别越高数值越小，配置的logLevel越大，记录的日志也越多    
         **/
        'logger' => array(
            'logOn'         => true,
            'logLevel'      => 5,
            //'logInShell'    => false,
            'logInShell'    => true,
            'logFormatMonth'=> false,
            // 按天拆日志
            'logFormatDay'  => true,
            // 是否记录PHP backtrace信息
            'backtrace'     => true,
        ),
    ),
    
    // global var,用户自定义配置
    'globals' => array(
    ),
);
