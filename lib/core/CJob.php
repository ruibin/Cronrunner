<?php
/**
 * all job must extends CObject,if not you can't use 
 * the registered components
 * @package JobFramework
 * @author ruibin<hcb0825@126.com>
 * @author ruibin<hcb0825@126.com>
 * @since 2012-09
 */

abstract class CJob extends CObject {
    
    // configurable property
    protected $_jobName;
    // 一天是否允许运行两次
    protected $_jobOdayTwice = false; 
    // 用于接收Job额外参数,参数类型Job自定
    private $__params = array();
    // !configurable property

    protected $_jobFile;
    protected $_jobFp;

    public function configurable()
    {
        return array('jobName', 'jobOdayTwice');
    }
    
    protected $_appInstance;
    public function getAppInstance() {
        return $this->_appInstance;
    }
    // when job is started this value is setted
    protected $_jobStartTime;
    protected $_lastJobEndTime;
    // when job is ended this value is setted
    protected $_jobEndTime;
    
	/**
     * The constructor
     */
    public function __construct($jobId)
    {
        parent::__construct();
        $this->_appInstance = CApp::getApp();
        $this->_jobName = $jobId;
        $this->_jobFile = ".$this->_jobName";
    }

    #region magic methods

    public function __set($key, $value)
    {
        $app = CApp::getApp();
        if ($this->hasWriteableProperty($key)) {
            parent::__set($key, $value);
        } else {
            $app->$key = $value;
        }
    }

    public function __get($key)
    {
        $app = CApp::getApp();
        if ($this->hasReadableProperty($key)) {
            return parent::__get($key);
        } else {
            return $app->$key;
        }
    }

    public function __call($method, $args)
    {
        if (method_exists($this, $method)) {
            return $this->$method($args);
        } else {
            return call_user_func_array(array(CApp::getApp(), $method), $args);
        }
    }
    
    /**
     * before the job really working
     * do something like make a job log record or make a request to monitoring system
     */
    public function onPreJob()
    {
        $this->_jobStartTime = date("Y-m-d H:i:s");
        $today = date("Y-m-d");
        if (file_exists($this->_jobFile)) {
            $fgct = file_get_contents($this->_jobFile);
            if ($fgct) {
                $tmp = explode(' ', $fgct); 
                if (!is_array($tmp)) {
                    exit('跟踪Job运行时间的文件损坏'); 
                }
                if (empty($tmp) || count($tmp) < 2) {
                    exit('跟踪Job运行时间的文件损坏'); 
                }
                $this->_lastJobEndTime = $fgct;
                $last_day = $tmp[0];
                if ($this->_jobOdayTwice === false)  {
                    if (strtotime($last_day) >= strtotime($today)) {
                        exit('无任务'); 
                    }
                }
            }
        }
        $this->_jobFp = fopen($this->_jobFile, 'w');
        if (!flock($this->_jobFp, LOCK_EX|LOCK_NB)) {
            exit('job is running!'); 
        }
    }

    public function onEndJob()
    {
        $this->_jobEndTime = date("Y-m-d H:i:s");
        fwrite($this->_jobFp, $this->_jobEndTime);
        flock($this->_jobFp, LOCK_UN);
        fclose($this->_jobFp);
    }
    
    abstract function jobAction();

    public function setParams($_params) {
        if (!is_array($_params)) {
            exit('job parameter must be array type'); 
        }
        $this->__params = $_params; 
    }

    public function getParams($name) {
        if (!empty($this->__params[$name])) {
            return $this->__params[$name]; 
        } else {
            return null;
        }
    }

    public function getAllParams() {
        return $this->__params;
    }

    public function _getJobName() {
        return $this->_jobName;
    }

    public function _setJobName($_jobName) {
        $this->_jobName = $_jobName;
    }

    public function _getJobOdayTwice() {
        return $this->_jobOdayTwice;
    }

    public function _setJobOdayTwice($_jobOdayTwice = false) {
        $this->_jobOdayTwice = $_jobOdayTwice;
    }
}
