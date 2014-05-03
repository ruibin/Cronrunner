<?php
/**
 * 
 * @package Framework
 * @author hongchuanbin<hcb0825@126.com>
 * Application class manage the program life cycle, handle exceptions and store
 * all components.
 *
 * All exceptions and php errors whose error type is contained in
 * 'error_reporting' config will be handled by application. But how to display
 * errors is determined by child classes. Display errors, log errors and even
 * error handle mechanism is optional an can be turned off.
 *
 * Components is subclass of CObject. Before use a component, make sure it is in
 * the 'requiredComponents' list (see {@link CApp::requiredComponents}) or it is
 * setted in configuration file. Also, you can set component config during runtime.
 * 
 * All components are magic property of CApp class, and will not be created
 * until first using.
 */
class CApp extends CObject {
    // system parameters can be set automatically
    // application name
    protected $_appName;
    // bool 
    protected $_displayErrors;
    
    protected $_errorReporting;
    
    // bool
    protected $_logBacktrace;
    
    // array() default logger config for error/exception handler
    protected $_defaultLoggerConf;
    // modules & it's config
    protected $_modules;
    
    // global variables
    protected $_globals;
    // array,need extension check before application
    protected $_needExtensions;
    // switch of extension check action, bool
    protected $_extensionSwitch;
    protected $_memLimit = "";
    // !system parameters can be set automatically
    
    // store all components objects
    protected $_mlds;
    
    protected $_systemRoot;
    protected $_systemLibCoreDir;
    protected $_systemLibModuleDir;
    // static function tools
    protected $_systemUtilsDir;
    protected $_systemLogDir;
    protected $_systemTmpDir;
    protected $_systemDataDir;
    
    protected $_userLibDir;
    protected $_userJobDir;
    
    // app instance
    protected static  $_appInstance;
    
    /**
     * the job name to run & must be same with job file name
     * @property string
     */
    protected $_jobId;
    public function _getJobId() {
        return $this->_jobId;
    }
    
    /**
     * job instance
     * @property Object
     */
    protected $_jobInstance;
    
    /**
     * The constructor.
     * @param string $appRoot
     * @param array $config
     */
    public function __construct($appRoot, $systemConfig, $userLibDir = NULL, $userJobDir = NULL)
    {
        parent::__construct($systemConfig);
        $this->_systemRoot = $appRoot;
        if ($userLibDir === NULL) {
            $this->_userLibDir = $this->_systemRoot.DIRECTORY_SEPARATOR."include";
        } else {
            $this->_userLibDir = $userLibDir;
        }
        if ($userJobDir === NULL) {
            $this->_userJobDir = $userJobDir;
        }
        
        // set system environment
        $this->_systemLibCoreDir = $this->_systemRoot.DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR.
                                   "core";
        $this->_systemLibModuleDir = $this->_systemRoot.DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR.
                                     "component";
        $this->_systemUtilsDir = $this->_systemRoot.DIRECTORY_SEPARATOR."lib".
            DIRECTORY_SEPARATOR."utils";
        $this->_systemLogDir = $this->_systemRoot.DIRECTORY_SEPARATOR."log";
        if (!file_exists($this->_systemLogDir)) {
            mkdir($this->_systemLogDir); 
        }
        $this->_systemTmpDir = $this->_systemRoot.DIRECTORY_SEPARATOR."tmp";
        if (!file_exists($this->_systemTmpDir)) {
            mkdir($this->_systemTmpDir); 
        }
        $this->_systemDataDir = $this->_systemRoot.DIRECTORY_SEPARATOR."data";
        if (!file_exists($this->_systemDataDir)) {
            mkdir($this->_systemDataDir); 
        }
        // !set system environment
    }
    #region magic methods
    public function __get($key)
    {
        if (isset($this->$key)) {
            return $this->$key; 
        }
        if ($this->hasComponent($key)) {
            return $this->getComponent($key);
        } else {
            return parent::__get($key);
        }
    }

    public function __call($method, $args)
    {
        trigger_error("Can not fine method '$method' in application object.", E_USER_ERROR);
    }
    #region magic methods
    public static function  create($app_root, $systemConfig, $userLibDir = NULL, $userJobDir = NULL)
    {
        if (!isset(self::$_appInstance)) {
            self::$_appInstance = new self($app_root, $systemConfig);
        }
        return self::$_appInstance;
    }
    
    public static function getApp()
    {
        return self::$_appInstance;
    }
    
    public function init($jobName = NULL, $jobParams = array())
    {
        set_include_path(get_include_path().PATH_SEPARATOR.$this->_systemLibModuleDir);
        set_include_path(get_include_path().PATH_SEPARATOR.$this->_userLibDir);
        //date_default_timezone_set('Asia/ShangHai');
        date_default_timezone_set('PRC');
        ini_set("display_errors", $this->_displayErrors);
        if (!empty($this->_memLimit)) {
            ini_set("memory_limit", $this->_memLimit);
        }
        error_reporting($this->_errorReporting);
        
        // error & exception handler
        set_error_handler(array('CErrorHandler', 'errorHandler'), error_reporting());
        set_exception_handler(array('CErrorHandler', 'exceptionHandler'));
        if (!empty($jobName)) {
            $this->_jobId = $jobName;
            $this->makeJobInstance($jobParams);
        }
    }
    
    /**
     * the main() function for the job
     * & when you run something isn't a job you don't need to invoke this function
     * @param string $jobName,file name of the running job
     */
    public function run()
    {
        $this->onPreApplication();
        $this->jobRun();
    }
    
    protected function onPreApplication()
    {
        if ($this->_extensionSwitch) {
            // extension check
            $missing_exts = array();
            foreach ($this->_needExtensions as $_extensionName) {
                if (!extension_loaded($_extensionName)) {
                    $missing_exts[] = $_extensionName;
                }
            }
            if (!empty($missing_exts)) {
                $str = "the missing php extension:".implode(",", $missing_exts);
                exit($str);
            }
            // !extension check
        }
    }
    
    protected function makeJobInstance($jobParams = array())
    {
        $jobName = $this->_jobId;
        $classFile = $this->_userLibDir.DIRECTORY_SEPARATOR.$jobName.".php";
        if (file_exists($classFile)) {
            require $classFile;
            $className = "{$jobName}Job";
            $this->_jobInstance = new $className($this->_jobId);
            if (!$this->_jobInstance instanceof CJob) {
                trigger_error("job {$jobName} isn't instance of CJob", E_USER_ERROR);
            }
            if (!empty($jobParams)) {
                $this->_jobInstance->setParams($jobParams); 
            }
        } else {
            trigger_error("the job {$this->_jobId} dosen't exists", E_USER_ERROR);
        }
    }
    
    public function jobRun()
    {
        $this->_jobInstance->onPreJob();
        $run_rst = $this->_jobInstance->jobAction();
        if ($run_rst === 1 || $run_rst === true) {
            $this->_jobInstance->onEndJob();
        }
    }
    
    /**
    * @see CObject::hasReadableProperty()
    */
    public function hasReadableProperty($key)
    {
        return $this->hasComponent($key) || parent::hasReadableProperty($key);
    }
    
    
    public function hasComponent($id)
    {
        return isset($this->_mlds[$id]) || isset($this->_modules[$id]);
    }
    
    /**
     * dynamiclly load components
     * @param string $id
     */
    public function getComponent($id)
    {
        if (!isset($this->_mlds[$id])) {
            if (!isset($this->_modules[$id])) {
                trigger_error("Thers is no component '$id'.", E_USER_ERROR);
            }
            $component = $this->_modules[$id];
            //var_dump($component);
            //exit();
            $className = 'C' . ucfirst($id);
            $classFile = $className.".php";
            if (isset($component['class'])) {
                $classFile = $component['class'].".php";
                $className = $component['class'];
            } 
            unset($component['class']);
            if (!CAutoLoader::filexists($classFile)) {
                trigger_error("class $className doesn't exists", E_USER_ERROR);
            }
            $this->_mlds[$id] = new $className($component);
        }
        return $this->_mlds[$id];
    }
    
    public function configurable() 
    {
        return array(
            'appName',
            'displayErrors',
            'errorReporting',
            'logBacktrace',
            'defaultLoggerConf',
            'modules',
            'globals',
            'needExtensions',
            'extensionSwitch',
            'memLimit',
        );
    }
    /*******************************************getter & setter****************************************/
    // configurable system config
    public function _getAppName() 
    {
        return $this->_appName;
    }
    public function _getDisplayErrors() 
    {
        return $this->_displayErrors;
    }
    public function _getErrorReporting() 
    {
        return $this->_errorReporting;
    }
    public function _getLogBacktrace() 
    {
        return $this->_logBacktrace;
    }
    public function _getDefaultLoggerConf() 
    {
        return $this->_defaultLoggerConf;
    }
    public function _getModules() 
    {
        return $this->_modules;
    }
    public function _getGlobals() 
    {
        return $this->_globals;
    }
    public function _getNeedExtensions() 
    {
        return $this->_needExtensions;
    }
    public function _setAppName($_appName) 
    {
        $this->_appName = $_appName;
    }
    public function _setDisplayErrors($_displayErrors) 
    {
        $this->_displayErrors = $_displayErrors;
    }
    public function _setErrorReporting($_errorReporting)
    {
        $this->_errorReporting = $_errorReporting;
    }
    public function _setLogBacktrace($_logBacktrace) 
    {
        $this->_logBacktrace = $_logBacktrace;
    }
    public function _setDefaultLoggerConf($_defaultLoggerConf) 
    {
        $this->_defaultLoggerConf = $_defaultLoggerConf;
    }
    public function _setModules($_modules) 
    {
        $this->_modules = $_modules;
    }
    public function _setGlobals($_globals) 
    {
        $this->_globals = $_globals;
    }
    public function _setNeedExtensions($_needExtensions) 
    {
        $this->_needExtensions = $_needExtensions;
    }
    public function _getExtensionSwitch() 
    {
        return $this->_extensionSwitch;
    }
    public function _setExtensionSwitch($_extensionSwitch) 
    {
        $this->_extensionSwitch = $_extensionSwitch;
    }
    public function _getMemLimit() 
    {
        return $this->_memLimit;
    }
    public function _setMemLimit($_memLimit) 
    {
        $this->_memLimit = $_memLimit;
    }
    // !configurable system config
    
    /**
     * @return the $_systemRoot
     */
    public function _getSystemRoot() 
    {
        return $this->_systemRoot;
    }

    /**
     * @return the $_systemLibDir
     */
    public function _getSystemLibCoreDir() 
    {
        return $this->_systemLibCoreDir;
    }
    public function _getSystemLibModuleDir() 
    {
        return $this->_systemLibModuleDir;
    }
    public function _getSystemLogDir() 
    {
        return $this->_systemLogDir;
    }
    public function _getSystemTmpDir() 
    {
        return $this->_systemTmpDir;
    }
    public function _getSystemDataDir() 
    {
        return $this->_systemDataDir;
    }

    /**
     * @return the $_userLibDir
     */
    public function _getUserLibDir() 
    {
        return $this->_userLibDir;
    }

    /**
     * @return the $_userJobDir
     */
    public function _getUserJobDir() 
    {
        return $this->_userJobDir;
    }
    

    
/*******************************************getter & setter****************************************/
}
