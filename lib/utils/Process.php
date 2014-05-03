<?php
/**
 * process handle class
 * @author hcb0825@126.com
 * @since 2012-04
 */
class Process {
    
    /**
     * Run As Deamon
     * @param $pid_file pid file
     * @param $only_one limit only one
     */
    public static function run_as_deamon($pid_file, $only_one = true)
    {
        self::limit_cli_only();
        /**
         * father get child's pid
         * child get 0
         * error get -1
         * @see http://cn2.php.net/manual/en/function.pcntl-fork.php
         */
        $pid_fork = pcntl_fork();
        $pid_fork == -1 && die("fork error\n");
        $pid_fork != 0 && exit; // detach the process from terminal
        /**
         * make the current process as the session leader
         * totally get rid of the terminal session
         * @see http://cn2.php.net/manual/en/function.posix-setsid.php
         */
        posix_setsid() == -1 && die("could not detach from terminal\n");
        // pid file must have
        empty($pid_file) && die("daemon process must have the pid file\n");
        // create the file if not exists
        self::recursive_touch($pid_file);
        // get the pid number
        $pid = file_get_contents($pid_file);
        // check alive
        if(!empty($pid) && self::is_alive($pid) && $only_one) {
            die("process is running , limit only one\n");
        }
        // write pid
        file_put_contents($pid_file, posix_getpid());
    }
    
    /**
     * 限制为只允许命令行调用
     */
    public static function limit_cli_only()
    {
        if( substr(php_sapi_name(), 0, 3) != 'cli' ) {
            die("You Should Use CGI/CLI To Run This Program.\n");
        }
    }
    
    /**
     * touch the file 
     * if folder is not exists, it will create the folder
     * @param string $filename
     */
    public static function recursive_touch($filename) 
    {
        $dirname = dirname($filename);
        clearstatcache();
        !is_dir($dirname) && mkdir($dirname, 0755, true);
        clearstatcache();
        !is_file($filename) && touch($filename);
    } 
    
    /**
     * send process signal
     * @param int $pid
     * @param int $signal
     * @param bool $is_halt
     * @return mix
     */
    public static function kill($pid, $signal, $is_halt = false)
    {
        posix_kill($pid, $signal);// sent signle, kill -0
        $error_number = posix_get_last_error();
        if($error_number != 0 && $is_halt) {
            die("[kill -$signal $pid] Error: [$error_number] ".posix_strerror($error_number)."\n");
        }
        return ($error_number == 0) ? true : array($error_number, posix_strerror($error_number));
    }
    
    /**
     * kill by pid file 
     * if success pid file will be removed
     * @param string $pid_file
     */
    public static function kill_by_pidfile($pid_file)
    {
        empty($pid_file) && die("process must have a pid file, pid file must not be empty\n");
        clearstatcache(); /* clear cachce */
        // exists check
        if(!is_file($pid_file)) { 
            echo "pid file: [$pid_file] not exists\n";
            return;
        }
        // get pid number
        $pid = file_get_contents($pid_file);
        // terminate the process
        self::kill($pid, 9, true);
        // delete the pid file
        unlink($pid_file);
    }
    
    /**
     * is pid alive
     * @param int $pid
     * @return bool or exit
     */
    public static function is_alive($pid) 
    {
        if(empty($pid)) {return false;}
        $pid_status = self::kill($pid, 0);
        //$pid_status = posix_kill($pid, 0);
        if($pid_status === true) {return true;}
        if($pid_status[0] == 3) {return false;}
    } 
    
    /**
     * detach process
     * @param array $father
     * @param array $child
     * @return mix
     */
    public static function process_detach($father, $child) 
    {
        /**
         * father get child's pid
         * child get 0
         * error get -1
         * @see http://cn2.php.net/manual/en/function.pcntl-fork.php
         */
        $pid_fork = pcntl_fork();
        $pid_fork == -1 && die("fork error\n"); 
        // child get 0
        if($pid_fork == 0) {
            if(empty($child)) return;
            switch ($child['func']) {
                case 'exit':
                    exit; 
                case 'return':
                    return $pid_fork; 
                default: break;
            }
            /**
             * child call the very function
             * @see http://cn2.php.net/manual/zh/language.types.callable.php
             */
            return call_user_func_array($child['func'], isset($child['param']) ? $child['param'] : array());
        } else {
            if(empty($father)) return;
            switch ($father['func']) {
                case 'exit':
                    exit; 
                case 'return':
                    return $pid_fork; 
                default: break;
            }
            /**
             * father call the very function
             * @see http://cn2.php.net/manual/zh/language.types.callable.php
             */
            return call_user_func_array($father['func'], isset($father['param']) ? $father['param'] : array());
        }    
        
    }
}
