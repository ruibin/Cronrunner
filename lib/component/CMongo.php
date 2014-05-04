<?php
/**
 * @author ruibin<hcb0825@126.com>
 * @author ruibin<hcb0825@126.com>
 * @since 2012-09
 */
class CMongo extends CObject {
    // configurable property
    /**
     * mongo 数据库名
     * @property array('dsn', 'options') $_mongoConf
     */
    protected $_mongoConf ;
    
    protected $_counterCollection = "myCounter";
    // !configurable property
    
    protected $_mongoConn;
    protected $_mongoDb;
    
    function __destruct()
    {
        if (!empty($this->_mongoConn) && $this->_mongoConn instanceof  MongoClient) {
            $this->_mongoConn->close();
            $this->_mongoDb = null;
        }
    }
    
    function __construct($config = array())
    {
        parent::__construct($config);
        $this->connect();
    }
    
    public function connect()
    {
        if (!isset($this->_mongoConf['dsn']) || !isset($this->_mongoConf['options'])) {
            trigger_error("the keys dsn,options is necessary in mongo config", E_USER_NOTICE);
        }
        $dsn_db = substr(strrchr($this->_mongoConf['dsn'], '/'), 1);
        $mongoOptions = $this->_mongoConf['options'];
        if (!isset($mongoOptions['db'])) {
            trigger_error("the key db is necessary for mongo option", E_USER_NOTICE);
        }
        $opt_db = $mongoOptions['db'];
        if ($opt_db != $dsn_db) {
            $mongoOptions['db'] = $dsn_db; 
        }
        if (empty($this->_mongoConn)) {
            $this->_mongoConn = new MongoClient($this->_mongoConf['dsn'], $mongoOptions);
        }
        
        $dbName = $opt_db;
        $this->_mongoDb = $this->_mongoConn->$dbName;
    }
    
    /**
     * 实现计数器，自增长
     * @param string $tableName
     * @param int $cardinalNumber 计数器基数
     * @return int 
     */
    public function getCounter($cardinalNumber = NULL)
    {
        if (empty($this->_mongoConn)) {
            $this->connect();
        }
        // 设置计数器基数
        if ($cardinalNumber !== NULL) {
            if ($cardinalNumber <= 0) {
                return -1;
            }
            // 先查找相应表记录是否存在，不存在说明计数器第一次使用,然后设置基数
            $collectionName = $this->_counterCollection;
            $findRes = $this->_mongoDb->$collectionName->findOne(array('collection' => $collectionName));
            if ($findRes === NULL) {
                $find = array('collection' => $collectionName);
                $set = array('$set' => array('collection' => $collectionName, 'current' => $cardinalNumber));
                $this->_mongoDb->$collectionName->insert();
            }
        }
        // !设置计数器基数
        $result = $this->_mongoDb->command(array('findAndModify' => $this->_counterTable,
            'query' => array('collection' => $this->_counterCollection),
            'fields' => array('current' => true),
            'update' => array('$inc' => array('current' => 1)),
            'upsert' => true,
            'new' => true
        ));
        return $result['value']['current'];
    }
    
    public function configurable()
    {
        return array(
            'mongoConf',
            'counterCollection',
        );
    }
    
    public function _getMongoConf() 
    {
        return $this->_mongoConf;
    }
    public function _setMongoConf($_mongoConf) 
    {
        $this->_mongoConf = $_mongoConf;
    }
    public function _getCounterCollection() 
    {
        return $this->_counterCollection;
    }
    public function _setCounterCollection($_counterCollection) 
    {
        $this->_counterCollection = $_counterCollection;
    }
    public function _getMongoConn()
    {
        return $this->_mongoConn;
    }
    public function _getMongoDb()
    {
        return $this->_mongoDb;
    }
}
