Cronrunner
==========

php cli framework,companont manager,scalable tools

##Requirement
- PHP 5 +
- pcntl

## Tutorial

### layout

```
- config.php // Application config
- common.php
+ lib // Application library
  | + core // core library
  | + component // Configurable compnent
  | + utils // some tools,you must explicitly include them 
+ include // User defined library
+ bin // application
+ log 
+ tmp
+ data
+ www // www virtual directory 
```
### example

#### bin/testFramework.php

```
<?php
require '../common.php';
$app = CApp::create($appRoot, $sysConfig);
$app->init('TestFramework');// Job Factory, or **$app->init('TestFramework', $params_array);**to set some parameters
$app->run();
```

#### include/TestFramework.php

```
<?php
class TestFrameworkJob extends CJob {
    public function jobAction()
    {
        echo "hello framework!\n";
        sleep(10);
        // using getAllParams() to get all Job parameters
        // var_dump($this->getAllParams());
        // var_dump($this->getParams('test'));
        $this->_appInstance->logger->log("test log INFO", "NOTICE");
        return 1;
    }
}
```

### configurable modules

```here is a example of module config
// module config section
'modules' => array(
    // @logger:ERROR->NOTICE->WARNNING->EXCEPTION->INFO->ALL
    // log level does inverse relationship with **LogLevel** number value
    'logger' => array(
        'logOn'         => true, // logger switcher
        'logLevel'      => 5,
        // print in screen if it's setted to true
        'logInShell'    => true,
        // logrotate by month switcher
        'logFormatMonth'=> false,
        // logrotate by day switcher
        'logFormatDay'  => true,
        // backtrace swithcer
        'backtrace'     => true,
    ),
    // Mongo config example 
    'mongo_test' => array(
        'class' => 'CMongo',    
        'mongoConf' => array(
            // db is the database name to authentica
            'dsn' => 'mongodb://user:psw@host:port/db',
            // db1 is the database to select 
            'options' => array('db' => 'db1', 
                'connectTimeoutMS' => 5000, 'w' => 1,
            ),
        ),
    ),
),

### module using example

``` include/MongoTest.php
<?php
// class name must be same to file name
class MongoTest extends CJob {
    public function jobAction() {
        // Mongodb connection handler,lazy connection
        $db = $this->_appInstance->mongo_test->mongoDb;     
        $collection = $this->getParams('collection');
        var_dump($db->$collection->findone());
    }
}
```
```bin/mongo_test.php
<?php
$inc_file = sprintf('%s/../common.php', dirname(__FILE__));
require $inc_file;
$app = CApp::create($appRoot, $sysConfig);
$args = array('collection' => $argv[1]);
$app->init('MongoTest', $args);
$app->run();
```

