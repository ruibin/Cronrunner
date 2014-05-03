<?php
/**
 * @desc:示例
 * @author:hongchuabin<hcb0825@126.com>
 **/
class TestFrameworkJob extends CJob {
    public function jobAction()
    {
        echo "hello framework!\n";
        sleep(10);
        $this->_appInstance->logger->log("test log INFO", "NOTICE");
        return 1;
    }
}
