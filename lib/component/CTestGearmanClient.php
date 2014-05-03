<?php
class CTestGearmanClient extends CGearmanClientBase {
    public function clientCreateTasks()
    {
        $worker_count = $this->_gearmanForkCount;
        $tmp = "Hello World";
        for ($i = 0; $i < $worker_count; $i++){
            $worker_key = $i+1;
            $init_data[0] = $tmp." ".$worker_key;
            $this->task_data[] = $init_data;
        }
    }
    
    public function workerRunTasksOk($worker_run_rlts)
    {
        echo "have {$this->worker_return_ok_count} strrev res\n";
        echo "COMPLETE: " .  "\n";
        var_dump($worker_run_rlts);
    }
    
    public function workerRunFail($task) {
        echo "FAILED: " . $task->jobHandle() . "\n";
    }
}