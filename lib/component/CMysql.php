<?php
class CMysql extends CDataBase {
    // init dsn,tablename,$this->_sqlTemplate
    public function preTreat($tableNames) 
    {
        // mysql:host=10.255.255.22;dbname=EmailSMSPlatformDB;charset=UTF8
        $this->_dsn = "mysql:host=";
        if (isset($this->_port) && $this->_port != 3306) {
            $this->_dsn .= $this->_server.";port=".$this->_port.";dbname=".$this->_dbName.
                           ";charset=".$this->_charset;
        } else {
            $this->_dsn .= $this->_server.";dbname=".$this->_dbName.
                           ";charset=".$this->_charset;
        }
        if (empty($this->_tableNames)) {
            if (!is_array($tableNames)) {
                $this->_tableNames[] = $tableNames;
            } else {
                $this->_tableNames = $tableNames;
            }
        }
//        var_dump($this->_dsn);
        foreach (self::$_commonQueryKeys as $_query) {
            $this->_sqlTemplate[$_query] = "";
        }
    }
    
    public function top($topNum)
    {
        trigger_error("mysql don't support top function, use limit query", E_USER_ERROR);
    }
}
