<?php
/**
 * gearman client抽象类
 * @author hcb0825@126.com
 * @since  2012/08/27
 */
abstract class CGearmanClientBase extends CObject {
    
    /**
     * gearman worker file lock handle
     * @var object
     */
    protected $pid_fp_lock;
    
    /**
     * @var BOOL $pidfile_is_locked 
     */
    protected $pidfile_is_locked = FALSE;
    

    /**
     * 进程锁文件路径
     * @var string
     */
    protected $_lockDir;
    public function getLockDir() {
        return $this->_lockDir;
    }
    
    // configurable property
    /**
     * pid 文件名
     * @var string
     */
    protected $_lockFileName;
    
    /**
     * gearman dsn,(host1:port1,host2:port2....)
     */
    protected $_gearmanDsn ;
    
    /**
     * gearman worker 开启的进程数同时执行指定的worker
     * @var int
     */
    protected $_gearmanForkCount;
    
    /**
     * worker注册的function name 前缀
     * @var string
     */
    protected $_gearmanFuncNamePre;
    
    /**
     * whether run tasks in background
     */
    protected $_gearmanTaskBackground = false;
    
    /**
     * an interval of time in milliseconds when run blocking tasks 
     * @var int $_gearmanTimeout
     */
    protected $_gearmanTimeout;
    // !configurable property
    
    public function configurable() {
        return array(
            'lockFileName',
            'gearmanDsn',
            'gearmanForkCount',
            'gearmanFuncNamePre',
            'gearmanTaskBackground',
            'gearmanTimeout',
        );
    }
    /**
     * 用于给worker的任务数据
     * @var array 
     */
    protected $task_data = array();
    
    /**
     * @var int $worker_return_ok_count 汇总的worker完成后的抓取并处理过的结果得到的有效个数
     */
    protected $worker_return_ok_count = 0;
    
    public function run()
    {
        $app = CApp::getApp();
        $this->_lockDir = $app->systemTmpDir;
        if (!isset($this->_lockFileName)) {
            $this->_lockFileName = $app->jobId;
        }
        $app = null;
        $this->pidFileLock();
        $this->clientCreateTasks();
        $this->clientRunTasks();
    }
    
    /**
     * 释放文件锁资源
     */
    public function __destruct()
    {
        if ($this->pid_fp_lock !== null) {
            if ($this->pidfile_is_locked) {
                flock($this->pid_fp_lock, LOCK_UN);
            }
            fclose($this->pid_fp_lock);
        }
    }
    
    /**
     * worker文件锁
     * @param string $pid_file_realpath pid file绝对路径
     */
    public function pidFileLock($pid_file_realpath = null)
    {
        $this->_lockFileName .= ".lock";
        $lock_file = $this->_lockDir.DIRECTORY_SEPARATOR.$this->_lockFileName;
        if ($pid_file_realpath !== null) {
            $lock_file = $pid_file_realpath;
        }
        // 为了避免lock句柄在函数执行完被释放，故使用类成员存放lock句柄
        $this->pid_fp_lock = fopen($lock_file, "w+");
        /*
         * http://cn2.php.net/manual/zh/function.flock.php
         * LOCK_EX 独占锁定
         * LOCK_NB 取消阻塞
         * 成功时返回 TRUE， 或者在失败时返回 FALSE.
         */
        if(flock($this->pid_fp_lock, LOCK_EX | LOCK_NB)) {
            $this->pidfile_is_locked = true;
        } else {
            exit($this->_lockFileName." aleady run! exit automatically\n");
        }
    }
    
    /**
     * //根据worker的总数生成$this->task_data数据
     */
    abstract public function clientCreateTasks();
    
    /**
     * 注册任务并且并行地运行任务
     */
    public function clientRunTasks(){
        $_client = new GearmanClient();
        echo "gearman dsn:{$this->_gearmanDsn}\n";
        $_client->addServers($this->_gearmanDsn);
        $_client->setCompleteCallback(array($this, 'extractWorkerRes'));
        $_client->setFailCallback(array($this, 'workerRunFail'));
        foreach ($this->task_data as $_k => $_task_data) {
            if (!is_array($_task_data)) {
                trigger_error("data for gearman worker must be array", "NOTICE");
            } 
            $workload = json_encode($_task_data);
            $worker_key_num = $_k+1;
            if ($this->_gearmanTaskBackground) {
                $_client->addTaskBackground($this->_gearmanFuncNamePre."_".$worker_key_num, 
                                            $workload);
            } else {
                $_client->setTimeout($this->_gearmanTimeout);
                $_client->addTask($this->_gearmanFuncNamePre."_".$worker_key_num, 
                                            $workload);
            }
        }
        if (!$_client->runTasks())
        {
            $app = CApp::getApp();
            $app->logger->log("client run tasks error:{$_client->error()}", "NOTICE");
            $app = null;
        }
    }
    
    /**
     * gearman worker处理完后返回给client,client接收worker处理的结果
     * 设置为client的回调函数,具体worker具体实现
     * @param GearmanTask $task
     */
    public function extractWorkerRes($task){
        $task_uniq_name = $task->unique();
        $task_worker_rlt_name = "worker_rlt_".$task_uniq_name;
        $$task_worker_rlt_name = array();
        $res = json_decode($task->data(), true);
        $count_res = count($res);
        $this->worker_return_ok_count += $count_res;
        $$task_worker_rlt_name = $res;
        $this->workerRunTasksOk($$task_worker_rlt_name);
    }
    
    /**
     * worker正常返回结果,具体worker具体处理
     * @param array() $worker_run_rlts
     */
    abstract public function workerRunTasksOk($worker_run_rlts);
    
    /**
     * worker失败时调用的回调函数
     */
    abstract public function workerRunFail($task);

    public function _getLockFileName() {
        return $this->_lockFileName;
    }

    public function _setLockFileName($_lockFileName) {
        $this->_lockFileName = $_lockFileName;
    }

    public function _getGearmanDsn() {
        return $this->_gearmanDsn;
    }

    public function _setGearmanDsn($_gearmanDsn) {
        $this->_gearmanDsn = $_gearmanDsn;
    }

    public function _getGearmanForkCount() {
        return $this->_gearmanForkCount;
    }

    public function _setGearmanForkCount($_gearmanForkCount) {
        $this->_gearmanForkCount = $_gearmanForkCount;
    }

    public function _getGearmanFuncNamePre() {
        return $this->_gearmanFuncNamePre;
    }

    public function _setGearmanFuncNamePre($_gearmanFuncNamePre) {
        $this->_gearmanFuncNamePre = $_gearmanFuncNamePre;
    }

    public function _getGearmanTaskBackground() {
        return $this->_gearmanTaskBackground;
    }

    public function _setGearmanTaskBackground($_gearmanTaskBackground) {
        $this->_gearmanTaskBackground = $_gearmanTaskBackground;
    }

    public function _getGearmanTimeout() {
        return $this->_gearmanTimeout;
    }
    public function _setGearmanTimeout($_gearmanTimeout) {
        $this->_gearmanTimeout = $_gearmanTimeout;
    }
}

