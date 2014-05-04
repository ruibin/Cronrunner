<?php 
/**
 * gearman worker抽象类
 * @author hcb0825@126.com
 * @author hcb0825@126.com
 * @since 2012-08-27
 */
if (version_compare(PHP_VERSION, "5.3.0") < 0) {
    declare(ticks = 1);
}
abstract class CGearmanWorkerBase extends CObject{
    // configurable property
    /**
     * gearman dsn,(host1:port1,host2:port2....)
     */
    protected $_gearmanDsn;
    
    /**
     * gearman worker 线程总数
     * @var int
     */
    protected $_gearmanForkCount;
    
    /**
     * @var $_workerFileName 这类worker的名字,对应PHP文件名
     */
    protected $_workerFileName;
    
    /**
     * worker注册的function name 前缀
     * @var string
     */
    protected $_gearmanFuncNamePre;
    
    /**
     * worker需要的额外参数, 可自定义
     * @var array('sleep_second_error','sleep_second_child_exit',...),
     * 'sleep_second_error'表示worker执行失败后sleep的秒数,默认60
     * 'sleep_second_child_exit'表示子进程退出后父进程sleep的秒数，防止父进程过快退出
     */
    protected $_gearmanWorkerParams = array();
    // !configurable property
    
    protected $_app;
    
    const WORKING_JOBS_DIRNANE = "working_gearman_jobs";
    
    /**
     * gearman 子进程注册gearman worker 函数名,根据一个前缀生成
     * @var string
     */
    protected $gearman_register_func_name;
    
    /**
     * gearman worker obj
     * @var obj
     */
    protected $gearman_worker_handle;
    // 把子进程pid存起来
    protected $_forkedPids = array();
    // 把所有子进程的gearman worker句柄保存起来
    protected $_workerHandles = array();
    /**
     * pid 文件夹路径
     * @var string
     */
    protected $_lockDir;
    
    
    /**
     * gearman worker file lock handle
     * @var object
     */
    protected $pid_fp_lock;
    
    /**
     * 存放被kill掉的子进程序列号
     * @var array()
     */
    protected $killed_sub_process_keys = array();

    /**
     * gearman worker 子进程序号
     * 大于等于1 小于等于子进程总数
     * @var int
     */
    protected $gearman_worker_serial_no;
    
    /**
     * 存放所有子进程的序号
     * @var $sub_process_keys
     */
    protected $sub_process_keys = array();
    
    /**
     * @var BOOL $pidfile_is_locked 
     */
    protected $pidfile_is_locked = FALSE;
    
    protected $_isSubProcess = false;
    
//    /**
//     * @param array('dsn','_workerFileName','worker_num','register_func_name_pre') $gearman_conf
//     * @param string $_lockDir pid文件存放的目录
//     * @param string $_pidFileName_pre 一个系列的worker名前缀
//     * @param string $worker_params 传给worker的额外参数
//     */
//    public function __construct($gearman_conf, $_lockDir, $_pidFileName_pre, $worker_params = array())
//    {
//        if (!is_array($gearman_conf) || !isset($gearman_conf['dsn'])
//            || !isset($gearman_conf['worker_num']) || !isset($gearman_conf['register_func_name_pre'])
//            || !isset($gearman_conf['_workerFileName'])
//            )
//            {
//                exit('wrong parameter 1');
//            }
//        $this->_gearmanDsn= $gearman_conf['dsn'];
//        $this->_workerFileName = $gearman_conf['_workerFileName'];
//        $this->_gearmanForkCount = $gearman_conf['worker_num'];
//        $this->_gearmanFuncNamePre = $gearman_conf['register_func_name_pre'];
//        $this->_lockDir = $_lockDir;
//        $this->_pidFileName = $_pidFileName_pre;
//        $this->_gearmanWorkerParams = $worker_params;
//    }
    
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
     * 启动gearman worker
     */
    public function gearmanFork()
    {
        // run as daemon
        $pid_fork = pcntl_fork();
        $pid_fork == -1 && die("fork error\n");
        $pid_fork != 0 && exit; // detach the process from terminal
        /**
         * make the current process as the session leader
         * entirely get rid of the terminal session
         * @see http://cn2.php.net/manual/en/function.posix-setsid.php
         */
        posix_setsid() == -1 && die("could not detach from terminal\n");
        // !run as daemon
//        if (version_compare(PHP_VERSION, "5.3.0") < 0) {
//            declare(ticks = 1);
//        }
        $app = CApp::getApp();
        if (!isset($this->_app)) {
            $this->_app = $app;
        }
        $this->_lockDir = $app->systemTmpDir;
        $app = null;
        // 主进程先加锁
        $main_process_pidfile = $this->_lockDir.DIRECTORY_SEPARATOR.$this->_workerFileName."_main.pid";
        $this->pidFileLock($main_process_pidfile);
        /**
         * Register signal listeners
         */
        $this->registerTicks();
//        sleep(1200);
        // 记录子进程退出次数
        $sub_process_exit_time = 0;
        // 产生多个进程
        for($i = 0; $i < $this->_gearmanForkCount; $i++) {
            $this->gearman_worker_serial_no = $i+1;
            $this->sub_process_keys[] = $this->gearman_worker_serial_no;
            /*
             * pcntl_fork()
             * 成功时，在父进程执行线程内返回产生的子进程的PID，
             * 在子进程执行线程内返回0。
             * 失败时，在 父进程上下文返回-1，不会创建子进程，并且会引发一个PHP错误。
             */
            $pid = pcntl_fork();
            // ERROR
            $pid == -1 && exit("could not fork");
            // 父进程
            if( $pid !== 0 ) {
                $this->_forkedPids[] = $pid;
                $sub_process_exit_time++;
                echo "i am making child process!\n";
                if ($i == $this->_gearmanForkCount-1) {
                    $sub_process_status = -1;
                    // when i use wait in main process the signal handle doesn't work
//                    $sub_pid = pcntl_wait($sub_process_status, WNOHANG);
                    echo "i am waiting for job!\n";
                    /**
                     * Since we got here, all must be ok, send a CONTINUE
                     */
                    
                    while (1) {
                        
                    }
                }
                continue;
            } else {
                $this->_isSubProcess = true;
                $this->registerTicks();
                // 子进程任务
                $this->gearmanWorkerRun($i+1);
            }
        }
    }
    
    public function sigHandler($signo)
    {
        var_dump($signo);
        if (!$this->_isSubProcess) {
            switch ($signo) {
                case SIGINT:
                    $this->gearmanRunOnceBeforeExit();
                    break;
                case SIGTERM:
                    $this->gearmanRunOnceBeforeExit();
                    break;
                case SIGUSR1:
                    $this->gearmanRunOnceBeforeExit();
                    break;
                case SIGCHLD:
                    echo "a subprocess exits\n";
            }
        }
    }
    
    /**
     * register signal & tickts
     */
    public function registerTicks() 
    {
        if (!$this->_isSubProcess) {
            // register signal,send signal to parent process
            // 使用ticks需要PHP 4.3.0以上版本
            echo "i am registering signal for main process!\n";
            
            // 安装信号处理器
            pcntl_signal(SIGINT, array($this, "sigHandler"));
            pcntl_signal(SIGTERM,  array($this, "sigHandler"));
            pcntl_signal(SIGUSR1, array($this, "sigHandler"));
            if (version_compare(PHP_VERSION, "5.3.0") >= 0) {
                pcntl_signal_dispatch();
            }
        } else {
            echo "i am registering signal for subprecesses!\n";
            pcntl_signal(SIGCHLD, array($this, "sigHandler"));
            if (version_compare(PHP_VERSION, "5.3.0") >= 0) {
                pcntl_signal_dispatch();
            }
        }
    }
    
    /**
     * 子进程执行worker包含的任务
     * @param int $worker_key_num
     */
    public function gearmanWorkerRun($worker_key_num)
    {
        $this->pidFileLock();
        $this->gearmanInit($worker_key_num);
        $this->initCommonWorker();
        while($this->gearman_worker_handle->work()) {
            // init error log
            $error_info = '';
            /*
             * GearmanWorker::work
             * Waits for a job to be assigned and then calls the appropriate callback function. 
             * Issues an E_WARNING with the last Gearman error if the return code is not one of GEARMAN_SUCCESS.
             */
//            if($this->gearman_worker->work() === true) { continue; }
            
            $return_code = $this->gearman_worker_handle->returnCode();
            
            if($return_code != GEARMAN_SUCCESS) {
                // xxx 
                $error_info = $this->gearman_worker_handle->getErrno().": ";
                $error_info .= $this->gearman_worker_handle->error();
                $app = $this->_app;
                if (!isset($this->_app)) {
                    $app = CApp::getApp();
                }
                $app->logger->log("gearman do work error:{$error_info}", "ERROR");
                if (isset($this->$_gearmanWorkerParams['sleep_second_error'])) {
                    sleep($this->_gearmanWorkerParams['sleep_second_error']);
                } else {
                    sleep(60);
                }
            } else {
                
            }
        }
    }
    
    /**
     * this function is used for ensuring running gearman worker not to be interrupted 
     */
    public function gearmanRunOnceBeforeExit()
    {
        echo "i am handling signal!\n";
        if (!isset($this->_app)) {
            $this->_app = CApp::getApp();
        }
//        var_dump($this->sub_process_keys);
        foreach ($this->sub_process_keys as $_k => $_subProcessKey) {
            $workeName = $this->_gearmanFuncNamePre."_".$_subProcessKey;
            $this->_app->logger->log("i am worker {$workeName},i am tring to run last worker before exit", "NOTICE");
            $dataDir = $this->_app->systemDataDir;
            $workingJobsDir = $dataDir.DIRECTORY_SEPARATOR.self::WORKING_JOBS_DIRNANE;
            if (!file_exists($workingJobsDir)) {
                mkdir($workingJobsDir);
            }
            $registerFuncName = $this->_gearmanFuncNamePre."_".$_subProcessKey;
            $workingJobFile = $workingJobsDir.DIRECTORY_SEPARATOR.$registerFuncName;
            var_dump($workingJobFile);
            if (!file_exists($workingJobFile)) {
                $this->_app->logger->log("i am worker {$workeName},i am not working when i am nicely killed", "NOTICE");
                // when job isn't running kill the working process
                posix_kill($this->_forkedPids[$_k], SIGKILL);
            } 
            else {
                echo "the worker $workeName is running\n";
            }
        }
    }
    
    public function gearmanInit($worker_key_num)
    {
        $this->gearman_register_func_name = $this->_gearmanFuncNamePre."_".$worker_key_num;
    }
    
    public function initCommonWorker()
    {
        $this->gearman_worker_handle = new GearmanWorker();
        $this->gearman_worker_handle->addServers($this->_gearmanDsn);
        $this->gearman_worker_handle->addFunction($this->gearman_register_func_name/*register*/, 
                                                  array($this, 'workerFunc')/*do*/
                                                 );
        $this->_workerHandles[] = $this->gearman_worker_handle;
    }
    
    /**
     * use kill command to stop process
     * it is unsafe and  particularly dangerous for running gearman worker
     */
    public function gearmanWorkerStop()
    {
        for($i = 0; $i < $this->_gearmanForkCount; $i++){
            $this->sub_process_keys[] = $i+1;
        }
        foreach ($this->sub_process_keys as  $_sub_key) {
            $shell_cmd = "ps aux | grep ".$this->_workerFileName. " |";
            $shell_cmd .= "grep -v 'grep' | awk '{print $2}' |";
            $sub_process_name = $this->_workerFileName."_".$_sub_key;
            $pid_file = $this->_lockDir.$this->_workerFileName."_".$_sub_key.".pid";
            $pid_cmd = "cat $pid_file";
            $pid = exec($pid_cmd);
            $shell_cmd .= " grep $pid";
            echo $shell_cmd."\n";
            //查看进程是不是存在
            $ps_res = exec($shell_cmd);
            if (empty($ps_res)) {
                echo "process $sub_process_name is not running!\n";
            } else {
                $kill_cmd = "kill $pid";
                exec($kill_cmd);
                $this->killed_sub_process_keys[] = $_sub_key;
                echo "process $sub_process_name is stopped\n";
            }
        }
    }
    
    public function stopGearmanWorkerBySig()
    {
//        if (empty($this->_forkedPids)) {
//            $this->gearmanWorkerStop();
//        } else {
//            foreach ($this->_forkedPids as $_subPid) {
//                posix_kill($_subPid, SIGUSR1);
//            }
//        }
        posix_kill(posix_getpid(), SIGUSR1);
    }
    
    // 检查gearman worker是否仍在运行
    public function gearmanWorkerStatus()
    {
        for($i = 0; $i < $this->_gearmanForkCount; $i++){
            $this->sub_process_keys[] = $i+1;
        }
        foreach ($this->sub_process_keys as  $_sub_key) {
            $shell_cmd = "ps aux | grep ".$this->_workerFileName. " |";
            $shell_cmd .= "grep -v 'grep' | awk '{print $2}' |";
            $sub_process_name = $this->_workerFileName."_".$_sub_key;
            $pid_file = $this->_lockDir.$this->_workerFileName."_".$_sub_key.".pid";
            $pid_cmd = "cat $pid_file";
            $pid = exec($pid_cmd);
            $shell_cmd .= " grep $pid";
//            echo $shell_cmd."\n";
            // 查看进程是不是存在
            $ps_res = exec($shell_cmd);
            if (empty($ps_res)) {
                echo "process $sub_process_name is not running!\n";
            } else {
                echo "process $sub_process_name is running:$ps_res\n";
            }
        }
    }
    
    /**
     * 停止所有的worker，包括父进程
     */
    public function stopAllProcess()
    {
        if (!isset($this->_lockDir)) {
            $app = $this->_app;
            if (!isset($this->_app)) {
                $app = CApp::getApp();
            }
            $this->_lockDir = $app->systemTmpDir;
        }
        //停止子进程
//        $this->gearmanWorkerStop();
        $this->stopGearmanWorkerBySig();
        
        //停止父进程
        $shell_cmd = "kill -0 ";
        $main_pid_file = $this->_lockDir.$this->_workerFileName."_main.pid";
        $pid_cmd = "cat $main_pid_file";
        $pid = exec($pid_cmd);
        $shell_cmd .= $pid;
        echo $shell_cmd."\n";
        $ps_res = exec($shell_cmd);
//        var_dump($ps_res);
        if (!empty($ps_res)) {
            echo "\nparent process of gearman worker {$this->_workerFileName} is not running\n";
        } else {
            $kill_main_cmd = "kill $pid";
            exec($kill_main_cmd);
            echo "all processes of gearman worker {$this->_workerFileName} is stopped\n";
        }
    }
    
    /**
     * worker文件锁
     * @param string $pid_file_realpath pid file绝对路径
     */
    public function pidFileLock($pid_file_realpath = null)
    {
        // 获取进程号
        $pid = posix_getpid();
        if ($pid_file_realpath === null) {
            $this->_gearmanForkCount > 1 && $this->_workerFileName .= "_".$this->gearman_worker_serial_no;
            $this->_workerFileName .= ".pid";
            $lock_file = $this->_lockDir.DIRECTORY_SEPARATOR.$this->_workerFileName;
        } else {
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
            fwrite($this->pid_fp_lock, $pid);
            $this->pidfile_is_locked = true;
        } else {
            exit($this->_workerFileName." aleady run! exit automatically\n");
        }
    }
    
    /**
     * 具体worker具体实现，执行任务时的回调函数
     * @param object $job
     */
    public function workerFunc($job) {
        // Began to write file before do work because i need a file to show the job is running
        if (!isset($this->_app)) {
            $this->_app = CApp::getApp();
        }
        $dataDir = $this->_app->systemDataDir;
        $workingJobsDir = $dataDir.DIRECTORY_SEPARATOR.self::WORKING_JOBS_DIRNANE;
        if (!file_exists($workingJobsDir)) {
            mkdir($workingJobsDir);
        }
        $workingJobFile = $workingJobsDir.DIRECTORY_SEPARATOR.$this->gearman_register_func_name;
        $workingFp = fopen($workingJobFile, 'w');
        fclose($workingFp);
        // remove file at end of work 
        unlink($workingJobFile);
        // do work
        return $this->doWork($job);

    }
    
    abstract protected function doWork($job);
    
    public function configurable()
    {
        return array(
            'gearmanDsn',
            'gearmanForkCount',
            'workerFileName',
            'gearmanFuncNamePre',
            'gearmanWorkerParams',
        );
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

    public function _getWorkerFileName() {
        return $this->_workerFileName;
    }
    public function _setWorkerFileName($_workerFileName) {
        $this->_workerFileName = $_workerFileName;
    }

    public function _getGearmanFuncNamePre() {
        return $this->_gearmanFuncNamePre;
    }
    public function _setGearmanFuncNamePre($_gearmanFuncNamePre) {
        $this->_gearmanFuncNamePre = $_gearmanFuncNamePre;
    }

    public function _getGearmanWorkerParams() {
        return $this->_gearmanWorkerParams;
    }
    public function _setGearmanWorkerParams($_gearmanWorkerParams) {
        $this->_gearmanWorkerParams = $_gearmanWorkerParams;
    }
}

