<?php
class CMssql extends CDataBase {
    // init dsn,tablename,$this->_sqlTemplate
    public function preTreat($tableNames) {
        // dblib:host=10.255.255.113;dbname=EmailSMSPlatformDB;charset=UTF8
        $this->_dsn = "dblib:host=";
        $this->_dsn .= $this->_server.":".$this->_port.";dbname=".$this->_dbName.
                       ";charset=".$this->_charset;
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
    
    public function batchInsert($data)
    {
        trigger_error("mssql server don't support batch insert", E_USER_NOTICE);
    }
    
    public function limit($limit)
    {
        trigger_error("mssql server don't support limit,please use top", E_USER_NOTICE);
    }
}