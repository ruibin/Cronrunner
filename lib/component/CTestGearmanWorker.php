<?php
class CTestGearmanWorker extends CGearmanWorkerBase {
    public function doWork($job) {
        $workLoad = $job->workload();
        $workData = json_decode($workLoad, true);
        echo "reicieve data:\n";
        var_dump($workData);
        $workData[0] = strrev($workData[0]);
        
        return json_encode($workData);
//        return strrev($workLoad);
    }
    
    public function initWorkerParam($workerParams = NULL) {
        
    }
    
}
