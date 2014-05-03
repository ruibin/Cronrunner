<?php
/**
 * Logger
 * if you set this as component of framework then the Logger config can be set automatically
 * all kinds of php interval error level is supported but the fatal error and the user defined
 * errors are also supported
 * don't worry about parallel working job using this logger because file lock is used
 * @author hongchuanbin
 * @since 2012-09
 */
class CLogger extends CObject {
    const LOGGER_OFF       = 0;
    const LOGGER_ERROR     = 2;
    const LOGGER_NOTICE    = 4;
    const  LOGGER_WARNNING  = 8;
    const LOGGER_EXCEPTION = 16;
    const LOGGER_INFO      = 32;
    const LOGGER_ALL       = 1024;
    //日志目录文件
    private $log_file = '';
    //日志时间格式
    private $log_date_format = 'Y-m-d H:i:s';
    
    // configurable
    /**
     * 是否开启日志
     * @var bool
     */
    private $_logOn;
    
    /**
     * 日志等级
     * 0：LOGGER_OFF
     * 2: LOGGER_ERROR
     * 4: LOGGER_NOTICE
     * 8: LOGGER_WARNNING
     * 16:LOGGER_EXCEPTION
     * 32:LOGGER_INFO
     * 1024:LOGGER_ALL
     * @var int $_logLevel
     */
    private $_logLevel;
    
    /**
     * 是否把日志打印到输出
     * @var bool $_logInShell
     */
    private $_logInShell;
    
    /**
     * @var $_logDir
     */
    private $_logDir;
    
    /**
     * 是否按日切分日志
     * @var bool
     */
    private $_logFormatMonth =  false;
    
    /**
     * 是否按月切分日志
     * @var bool 
     */
    private $_logFormatDay = false;
    /**
     * tracecode
     * @var bool
     */
    private $backtrace = true;
    // !configurable 
    
    
    /**
     * 日志文件前缀名
     * @var string $_logFilePre
     */
    private $_logFilePre;


    private $__handle;    

    function __destruct() {
        if ($this->__handle) {
            fclose($this->__handle); 
        }
    }
    
    /**
     * 设定日志目录及文件
     * @param string $log_file_name
     */
    private function setLogFile()
    {
        $this->setLogFilePre();
        if (!isset($this->_logDir) || !isset($this->_logFilePre)) {
            trigger_error("log dir can't be empty", E_USER_ERROR);
        }
        $this->log_file = $this->_logDir;
        if ($this->_logFormatMonth) {
            $this->log_file = $this->log_file.DIRECTORY_SEPARATOR.$this->_logFilePre."_".date('Y')."_".date('m');
        } else if ($this->_logFormatDay) {
            $log_month_dir = $this->log_file.DIRECTORY_SEPARATOR.date('Ym');
            if (!file_exists($log_month_dir)) {
                mkdir($log_month_dir);
            }
            $this->log_file = $log_month_dir."/".$this->_logFilePre."_".date('d');
        } else {
            $this->log_file = $this->log_file.$this->_logFilePre."_".date('Y');
        }
        if (!file_exists($this->log_file)) {
            $this->log_file .= ".log";
            @touch($this->log_file);
        }
    }
    
    /**
     * when you don't use default logger but through logger instance
     * this function must be invoked before write log
     * @param string $logFilePre
     */
    public function setLogFilePre($logFilePre = NULL)
    {
        $app = CApp::getApp();
        if (!isset($this->_logDir)) {
            $this->_logDir = $app->systemLogDir;
//            var_dump($this->_logDir);
        }
        if (!isset($this->_logFilePre)) {
            if (!empty($logFilePre)) {
                $this->_logFilePre = $logFilePre;
            } else {
                if (isset($app->jobId)) {
                    $this->_logFilePre = $app->jobId;
                } else {
                    $this->_logFilePre = $app->appName;
//                    var_dump($this->_logFilePre);
                }
            }
        }
        $app = null;
    }
    
    /**
     * 写日志
     * @param $level 
     * @param $message
     */
    private function write($level, $message, $backtrace)
    {
        $this->setLogFile();
        $reflector = new ReflectionClass(__CLASS__);
        $level     = strtoupper($level);
        $logging_level_code = $reflector->getConstant("LOGGER_".$level);
//        echo "log level $logging_level_code\n";
        //日志总开关
        if(!$this->_logOn){return;}
        static $func;
        if (!isset($func)) $func = create_function('$v', 'return is_string($v) ? "\"$v\"" : $v;');
        !empty($backtrace) && krsort($backtrace);
        /* backtrace */
        $error_reporting = ini_get('error_reporting');
        ini_set('error_reporting', 0);
        $trace_level = 0;
        foreach ($backtrace as $trace) {
          if (in_array($trace['class'], array(__CLASS__))) { continue; }
          $trace['args'] = implode(", ", array_map($func, $trace['args']));
          $message .= sprintf(PHP_EOL . "FunctionTrace[#%d]: %s%s%s(%s) in %s on line %s",
                             $trace_level++,     $trace['class'], $trace['type'], 
                             $trace['function'], $trace['args'],  $trace['file'], 
                             $trace['line']);
        }
        ini_set('error_reporting', $error_reporting);
        /* !backtrance */
        $message = sprintf("%-8s %s", "[$level]", $message);
        if ($this->_logInShell || ini_get('display_errors') == 'on') {
            echo sprintf("%s %s" . PHP_EOL, date("Y-m-d H:i:s"), $message);
        }
        if ($logging_level_code <= $this->_logLevel) {
            if (!$this->__handle) {
                $this->__handle = fopen($this->log_file, 'ab');
            }
            while (flock($this->__handle, LOCK_EX|LOCK_NB)) {
                fwrite($this->__handle, sprintf(PHP_EOL . "%s %s", date("Y-m-d H:i:s"), $message));
                flock($this->__handle, LOCK_UN);
                break;
            }
        }
    }
    
    /**
     * 清除日志
     * @param 日志目录 $log_file_name
     */
    private function rm($log_file_name = null)
    {
        $fp = fopen($this->log_file, 'wb');
        fwrite($fp, '');
        fclose($fp);
    }
    
    /**
     * 魔术函数调用生成“info|warning|notice|error|exception”类的日志
     * @param string $method
     * @param string $params
     */
    public function __call($method, $params)
    {
//        echo $method."\n";
        if (!preg_match_all("/^(info|warnning|notice|error|exception)$/i", $method, $matches)) {
            /* method not found */
            exit("Logger Function: " . __CLASS__ . "->$method() doesn't exists");
        }
        if ($this->_logLevel <= self::LOGGER_OFF) return;
        $reflector = new ReflectionClass(__CLASS__);
        $level     = strtoupper($matches[1][0]);
        $logging_level_code = $reflector->getConstant("LOGGER_".$level);
        if (!$logging_level_code) {
            exit("unsurpported logger name");
        }
        $message   = trim($params[0]);
        $backtrace = isset($params[1]) ? $params[1] : debug_backtrace();
        $this->write($level, $message, $this->backtrace ? $backtrace : array(), $logging_level_code);
    }
    
    /* 外部日志接口 */
    /**
     * 必须实例化后调用
     * @param string $content
     * @param string $level
     * @param string $file_basename
     */
    public function log($content, $level, $needDebugTrace = null) {
        $level = strtolower($level);
        if ($needDebugTrace === TRUE) {
            $this->$level($content);
        } else {
            $this->$level($content, array());
        }
        
    }
    
    /**
     * 静态接口
     * 供全局异常处理使用
     */
    public static function defaultLog($message, $log_type, $log_filename_pre = NULL) 
    {
        $app = CApp::getApp();
        $default_params = $app->defaultLoggerConf;
        $default_params['_logFilePre'] = $app->appName;
        $default_params['logDir'] = $app->systemLogDir;
        $log_tool = new self();
        foreach ($default_params as $_key => $_value) {
            $log_tool->$_key = $_value;
        }
        $backtrace = $app->logBacktrace?debug_backtrace():array();
        $log_tool->$log_type($message, $backtrace);
        unset($log_tool);
        $app = NULL;
    }
    public function configurable() {
        return array(
            'logOn',
            'logLevel',
            'logInShell',
            'logFormatMonth',
            'logFormatDay',
            'backtrace',
        );
    }
/*******************************************getter & setter****************************************/
    public function _getLogOn() {
        return $this->_logOn;
    }
    public function _getLogLevel() {
        return $this->_logLevel;
    }
    public function _getLogInShell() {
        return $this->_logInShell;
    }
    public function _getLogDir() {
        return $this->_logDir;
    }
    public function _getLogFormatMonth() {
        return $this->_logFormatMonth;
    }
    public function _getLogFormatDay() {
        return $this->_logFormatDay;
    }
    public function _getBacktrace() {
        return $this->backtrace;
    }
    public function _setLogOn($_logOn) {
        $this->_logOn = $_logOn;
    }
    public function _setLogLevel($_logLevel) {
        $this->_logLevel = $_logLevel;
    }
    public function _setLogInShell($_logInShell) {
        $this->_logInShell = $_logInShell;
    }
    public function _setLogDir($_logDir) {
        $this->_logDir = $_logDir;
    }
    public function _setLogFormatMonth($_logFormatMonth) {
        $this->_logFormatMonth = $_logFormatMonth;
    }
    public function _setLogFormatDay($_logFormatDay) {
        $this->_logFormatDay = $_logFormatDay;
    }
    public function _setBacktrace($backtrace) {
        $this->backtrace = $backtrace;
    }
/*******************************************getter & setter****************************************/
}
