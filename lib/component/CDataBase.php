<?php
/**
 * database driver & use pdo
 * @author chuanbin<hcb0825@126.com>
 * @since 2012-09
 */
abstract class CDataBase extends CObject
{
    /**
     * this is a comparison table bwtween driver query key and sql query key
     * the aggregation function is not included
     * this is common query keys
     * @var array() $_commonQueryKeys
     */
    static protected $_commonQueryKeys = array('select','top','from','join','where','group',
                                               'having','order','limit','insert','value','duplicate',
                                               'update','set','delete');
    static protected $_orderKeys = array('asc', 'desc');

    /**
     * subqueries keys in where
     * this is a comparison table bwtween driver query key and sql query key
     * the aggregation function is not included
     */
    static protected $_subWhereKeys =array('isnull' => "{key} IS NULL", 'nnull' => "{key} NOT NULL",
                                           'in' => "{key} IN ({1})", 'nin' => "{key} NOT IN ({1})",
                                           'ne' => "{key}!={1}", 'btand' => "{key} BETWEEN {1} AND {2}",
                                           'gt' => "{key}>{1}", 'lt' => "{key}<{1}", 
                                           'gte' => "{key} >={1}", 'lte' => "{key}<={1}",
                                           'llike' => "{key} like {1}", 'rlike' => "{key} like {1}", 
                                           'like' => "{key} like {1}",
                                           );
    static  protected $_subQuerySymbol = "$";
    
    // aggregation function keys
    static protected $_aggregationKeys = array('count','max','min','sum','avg');
    
    
    protected $_selectFields = array();
    // decides whether it is one record select
    protected $_isToFindOne = false;
    protected $_isBatchInsert = false;
    
    // aggregation function field,one field or empty is allowed
    protected $_aggregationType = "";
    protected $_aggregationField = "";

    // wherever it is nested query or not
    protected $_isNestedSelect = FALSE;
    protected $_sqlType;
    // sql template
    protected $_sqlTemplate = array();
    // prototype for PDO prepare
    protected $_sqlPrototype;
    
    // PDO Connect config,this is configurable
    protected $_server;
    public function _getServer() {
        return $this->_server;
    }
    public function _setServer($_server) {
        $this->_server = $_server;
    }
    
    protected $_port;
    public function _getPort() {
        return $this->_port;
    }
    public function _setPort($_port) {
        $this->_port = $_port;
    }
    
    protected $_dbName;
    public function _getDbName() {
        return $this->_dbName;
    }

    public function _setDbName($_dbName) {
        $this->_dbName = $_dbName;
    }
    
    protected $_charset;
    public function _getCharset() {
        return $this->_charset;
    }

    public function _setCharset($_charset) {
        $this->_charset = $_charset;
    }
    
    protected $_driverOptions;
    public function _getDriverOptions() {
        return $this->_driverOptions;
    }
    public function _setDriverOptions($_driverOptions) {
        $this->_driverOptions = $_driverOptions;
    }
    
    protected $_userName;
    public function _getUserName() {
        return $this->_userName;
    }
    public function _setUserName($_userName) {
        $this->_userName = $_userName;
    }
    
    protected $_password;
    public function _getPassword() {
        return $this->_password;
    }
    public function _setPassword($_password) {
        $this->_password = $_password;
    }
    
    protected $_debug = false;
    public function _getDebug() {
        return $this->_debug;
    }
    public function _setDebug($_debug) {
        $this->_debug = $_debug;
    }
    // !PDO Connect config,this is configurable
    
    // PDO DSN
    public $_dsn;
    // tables
    protected $_tableNames = array();
    
    // pdo object
    protected $_db;
    public function _getDb() {
        return $this->_db; 
    }
    protected $_error;
    public function _getError() {
        $_info = $this->_db->errorInfo(); 
        if (!empty($_info)) {
            return $_info[2]; 
        }
        return null;
    } 


    // array('bindName' => 'bindValue')
    protected $_bindParams = array();
    // need to bind names, all the names are numeric, array('bindName' => 'bindType')
    protected $_needToBindNames = array();
    
    
    // init dsn,tablename,$this->_sqlTemplate
    abstract public function preTreat($tableNames);
    
    public function resetTable($tableName) {
        $this->_tableNames = array();
        if (!is_array($tableName)) {
            $this->_tableNames[] = $tableName;
        } else {
            $this->_tableNames = $tableName;
        }
    }
    
    /*
     * @desc:public ACL for using PDO method directly
     **/
    public function connect()
    {
        if (empty($this->_db)) {
            $this->_db = new PDO($this->_dsn, $this->_userName, $this->_password, $this->_driverOptions);
        }
    }
    
    /**
     * when you use aggregation function like 'count' or sub query like 'in',auto call will be invoked
     */
    public function __call($method, $params)
    {
        $subWhereKeys = array_keys(self::$_subWhereKeys);
        if (!isset($this->_sqlType)) {
            $this->_sqlType = "find";
        }
        $function = $this->_sqlType;
        if ($this->_sqlType == "find" && in_array($method, self::$_aggregationKeys)) {
            //aggregation supportting
            $aggregationField = "1";
//            var_dump($params);
//            exit();
            if (isset($params) && !empty($params)) {
                $params = $params[0];
                if (is_string($params)) {
                    $aggregationField = $params;
                } else {
                    trigger_error("aggregation function parameter 1 error", E_USER_ERROR);
                }
            }
            $this->setAggregation($method, $aggregationField);
        } else if(in_array($method, $subWhereKeys)){
            $callParamter = array('$'.$method => $params);
            call_user_func_array(array($this, $this->_sqlType), $callParamter);
        } else {
            trigger_error("unsupported sql query key $method", E_USER_ERROR);
        }
    }
    
    protected function setAggregation($aggregationType, $aggregationField)
    {
        $this->_aggregationField = $aggregationField;
        $this->_aggregationType = $aggregationType;
        return $this;
    }
    
    /**
     * Enter description here ...
     * @param
     */
    public function find($query = array(), $fields = null)
    {
        $fieldStr = "*";
        if ($fields !== null) {
            if (!is_array($fields)) {
                $fields = array($fields);
            }
            $fieldStr = implode(",", $fields);
        }
        if (empty($this->_aggregationField) && empty($this->_aggregationType)) {
            $this->_sqlTemplate['select'] = "SELECT $fieldStr";
        }
        if (empty($this->_sqlTemplate['from'])) {
            $this->_sqlTemplate['from'] .= "FROM ".implode(",", $this->_tableNames);
        } else {
            $this->_sqlTemplate['from'] .= " ".implode(",", $this->_tableNames);
        }
        $this->_sqlType = "find";
//        var_dump($query);
//        exit();
        $this->parseFind($query, $fields);
        
        return $this;
    }
    
    public function findOne($query = array(), $fields = null)
    {
        $this->_isToFindOne = true;
        $this->_sqlType = "find";
        $this->_sqlTemplate['from'] .= implode(",", $this->_tableNames);
        $this->parseFind($query, $fields);
        return $this;
    }
    
    public function insert($data, $duplicateFields = null, $isReplace = false)
    {
        $this->_sqlType = "insert";
        $this->parseInsert($data, $duplicateFields, $isReplace);
        return $this;
    }
    public function batchInsert($data)
    {
        $this->_sqlType = "batchInsert";
        $this->_isBatchInsert = true;
        $this->parseInsert($data);
        return $this;
    }
    
    public function update($query, $set)
    {
        $this->_sqlType = "update";
        $this->parseUpdate($query, $set);
        return $this;
    }
    
    public function delete($query)
    {
        $this->_sqlType = "delete";
        $this->parseDelete($query);
        return $this;
    }
    
    public function order($orderField, $orderType)
    {
        if (is_array($orderField)) {
            $orderField = $orderField[0];
        }
        $orderType = strtolower($orderType);
        if (!in_array($orderType, self::$_orderKeys)) {
            trigger_error("wrong order type", E_USER_ERROR);
        }
        if ($this->_selectFields[0] !== "*" && !in_array($orderType, self::$_orderKeys)) {
            trigger_error("order by is wrong");
        }
        $orderType = strtoupper($orderType);
        $this->_sqlTemplate['order'] = "ORDER BY $orderField $orderType";
    }
    
    public function limit($limit, $offset = null)
    {
        if ($offset !== null) {
            if (is_int($offset)) {
                $this->parseBind($offset);
            } else {
                trigger_error("when you use limit function, offset must be numeric", E_USER_NOTICE);
            }
        }
        $this->parseBind($limit);
        if ($offset === null) {
            $this->_sqlTemplate['limit'] = "LIMIT ?";
        } else {
            $this->_sqlTemplate['limit'] = "LIMIT ?,?";
        }
        return $this;
    }
    
    public function top($topNum)
    {
        $this->parseBind($topNum);
        $this->_sqlTemplate['top'] = "TOP ?";
        return $this;
    }
    /**
     * join function
     * @param string $joinTable 
     * @param string the 'ON' condition field 
     * @param string $joinType @example left|right|inner|cross
     * @param array|string select columns from join table
     * @param string $joinToTable:src table to join
     */
    public function join($joinTable, $joinConditionField, $joinType = "", 
                         $joinField = "", $joinToTable = NULL)
    {
        if (empty($this->_sqlTemplate['from'])) {
            trigger_error("the join query can't be your first invoking", E_USER_ERROR);
        }
        if (!is_string($joinTable)) {
            trigger_error("the join table must be a invalid table name", E_USER_ERROR);
        }
        if ($joinToTable !== null && !is_string($joinToTable)) {
            trigger_error("the join table must be a invalid table name", E_USER_ERROR);
        }
        $srcTable = $joinTable;
        $targetTable = is_null($joinToTable)?$this->_tableNames[0]:$joinToTable;
        if ($srcTable == $targetTable) {
            trigger_error("can't join table itself", E_USER_ERROR);
        }
        if (!is_array($joinField)) {
            $joinField = array($joinField);
        }
        foreach ($joinField as $_field) {
            if (isset($_field) && !empty($_field)) {
                $this->_sqlTemplate['select'] .= ",$joinTable.$_field";
            }
        }
        $this->_selectFields[] = $joinConditionField;
        $this->_sqlTemplate['join'] .= "$joinType JOIN $srcTable ON $srcTable.$joinConditionField=$targetTable.$joinConditionField";
        return $this;
    }
    
    public function group($groupField)
    {
        if (empty($this->_selectFields) || !in_array($groupField, $this->_selectFields)) {
            trigger_error("can't use group at the first invoking this driver and the aggregation 
                          field must be in the select fields", E_USER_ERROR);
        } else {
            $this->_sqlTemplate['group'] = "GROUP BY $groupField";
            return $this;
        }
    }
    
    /**
     * parse having query
     * @param array() $queryCondition
     * @param string $aggregateType
     */
    public function having($queryCondition, $aggregateType = NULL) 
    {
        if (empty($this->_sqlTemplate['group'])) {
            trigger_error("having operation must be used with group", E_USER_ERROR);
        }
        list($_key, $_value) = $queryCondition;
        if (is_array($_value) && strpos($_key, self::$_subQuerySymbol) !== FALSE) {
            list($_havingField, $_compareValue) = $_value;
            if ($aggregateType !== null && in_array($aggregateType, self::$_aggregationKeys)) {
                $_havingField = $aggregateType."(".$_havingField.")";
            }
            $subHavingQuery = $this->parseSubWhereQueryKey($_key, $_havingField, $_compareValue);
            $this->_sqlTemplate['having'] = $subHavingQuery;
        } else {
            $_havingField = $_key;
            $_compareValue = $_value;
            if ($aggregateType !== null && in_array($aggregateType, self::$_aggregationKeys)) {
                $_havingField = $aggregateType."(".$_havingField.")";
            }
            $this->parseBind($_compareValue);
            $this->_sqlTemplate['having'] = "HAVING ".$_havingField."=?";
        }
        return $this;
    }
    
    /**
     * parse  some subqueries like '$in'
     * @param string $type query type
     * @param $field the field to compare
     * @param $value the value to compare, array or string
     * @return string 
     */
    protected function parseSubWhereQueryKey($type, $field, $value) 
    {
        if (strpos($type, self::$_subQuerySymbol) === FALSE) {
            trigger_error("the '$' symbol is necessary", E_USER_ERROR);
        }
//        var_dump($type, $value);
        $type = substr($type, 1);
        $allTypes = array_keys(self::$_subWhereKeys);
        if (!in_array($type, $allTypes)) {
            trigger_error("unsurpported query key $type", E_USER_ERROR);
        }
        $templates = self::$_subWhereKeys;
        $return_value = "";
        $template_value = $templates[$type];
        $field_pattern = "@\{key\}@";
        $field_replaced = preg_replace($field_pattern, $field, $template_value);
//        var_dump($field_replaced);
//        exit();
        $set_value_pattern = "@\{(?P<values>\d+)\}@";
        $toBinds = array();
        if (preg_match_all($set_value_pattern, $field_replaced, $toBinds)) {
//            var_dump($field_replaced);
            $toBinds = $toBinds['values'];
//            var_dump($toBinds);
//            exit();
            $param_int_pattern = "@^\d+(\.\d+)?$@";
            if (count($toBinds) > 1 && is_array($value)) {
                foreach ($toBinds as $_k => $_bindName) {
                    $this->parseBind($value[$_k]);
                }
                $return_value = preg_replace($set_value_pattern, "?", $field_replaced);
            } else {
                $tmp_str = "?";
                $this->parseBind($value);
                if (is_array($value)) {
                    $bind_array = array();
                    $bind_array = array_pad($bind_array, count($value), "?");
                    $tmp_str = implode(",", $bind_array);
//                    var_dump($tmp_str);
//                    exit();
                } 
                $return_value = preg_replace($set_value_pattern, $tmp_str, $field_replaced);
            }
        }
        return $return_value;
    }
    
    /**
     * prepare for PDO->bindValue
     * @param array|string $param
     */
    protected function parseBind($value) 
    {
        $param_int_pattern = "@^\d+(\.\d+)?$@";
        $paramType = PDO::PARAM_STR;
        if (is_array($value)) {
            $start_bind_num = empty($this->_needToBindNames)?1:(count($this->_needToBindNames)+1);
            foreach ($value as $_k => $_toNind) {
                if (preg_match($param_int_pattern, $_toNind)) {
                    $paramType = PDO::PARAM_INT;
                }
                $bindNum = $start_bind_num;
                $bindName = "$".$bindNum;
                $this->_needToBindNames[$bindName] = $paramType;
                $this->_bindParams[$bindName] = $_toNind;
                $start_bind_num++;
            }
        } else {
            if (preg_match($param_int_pattern, $value)) {
                $paramType = PDO::PARAM_INT;
            }
            if (empty($this->_needToBindNames)) {
                $this->_needToBindNames["$1"] = $paramType;
                $this->_bindParams["$1"] = $value;
            } else {
                $bindNum = count($this->_needToBindNames)+1;
                $bindName = "$".$bindNum;
                $this->_needToBindNames[$bindName] = $paramType;
                $this->_bindParams[$bindName] = $value;
            }
        }
    }
    
    protected function parseFind($queryCondition, $fields = NULL) 
    {
        if ($fields !== NULL) {
            if (!is_array($fields)) {
                $this->_selectFields[] = $fields;
            } else {
                $this->_selectFields = $fields;
            }
        }
        if (!is_array($queryCondition)) {
            trigger_error("parameter 1 of find method must be array", E_USER_ERROR);
        }
        $bindFieldsArr = array();
        foreach ($queryCondition as $_key => $_value) {
            if (is_array($_value) && strpos($_key, self::$_subQuerySymbol) !== FALSE) {
                $fields = array_keys($_value);
                $_field = $fields[0];
                $_compareValues = array_values($_value);
                $_compareValue =  $_compareValues[0];
//                var_dump($_field, $_compareValue);
//                exit();
                // special case for 'like' query
                if (strpos($_key, 'like') !== FALSE) {
//                    echo "like query:\n";
                    $tmp = substr($_key, 1);
                    switch ($tmp) {
                        case 'like':
                            $_compareValue = "%".$_compareValue."%";
                            break;
                        case 'llike':
                            $_compareValue = $_compareValue."%";
                            break;
                        case 'rlike':
                            $_compareValue = "%".$_compareValue;
                            break;
                        default:break;
                    }
                }
               $subQuery = $this->parseSubWhereQueryKey($_key, $_field, $_compareValue);
//                print $subQuery;
//                exit();
                if (!empty($this->_sqlTemplate['where'])) {
                    $this->_sqlTemplate['where'] .= " AND ".$subQuery;
                } else {
                    $this->_sqlTemplate['where'] .= "WHERE $subQuery";
                }
            } else if (!is_array($_value) && (strpos($_key, self::$_subQuerySymbol) === FALSE)){
                
                $this->parseBind($_value);
                $bindFieldsArr[] = $_key."=?";
//                foreach ($queryCondition as $_k => $_v) {
//                    $this->parseBind($_v);
//                    if (strpos($_k, self::$_subQuerySymbol) === FALSE) {
//                        $bindFieldsArr[] = $_k."=?";
//                    }
//
//                }
//                $bindStr = "";
//                if (count($bindFieldsArr) > 1) {
//                    $bindStr .= " ".implode(",", $bindFieldsArr);
//                } else {
//                    $bindStr .= " ".$bindFieldsArr[0];
//                }
//                if (!empty($this->_sqlTemplate['where'])) {
//                    $this->_sqlTemplate['where'] .= " AND ".$bindStr;
//                } else {
//                    $this->_sqlTemplate['where'] .= "WHERE $bindStr";
//                }
            } else {
                trigger_error("not supported query condition");
            }
        }
        if (!empty($bindFieldsArr)) {
            if (empty($this->_sqlTemplate['where'])) {
                $this->_sqlTemplate['where'] = 'WHERE '.implode(" AND ", $bindFieldsArr);
            } else {
                $this->_sqlTemplate['where'] .= " AND ".implode(" AND ", $bindFieldsArr);
            }
        }
    }
    
    protected function parseUpdate($query, $set) 
    {
        $tableStr = $this->_tableNames[0];
        $this->_sqlTemplate['update'] = "UPDATE {$tableStr}";
        if(!is_array($set)) {
            trigger_error("update function parameter 2 error,it must be array", E_USER_ERROR);
        }
        if (!is_array($set)) {
            trigger_error("update sql format error in setting value");
        }
        foreach ($set as $_key => $_value) {
            if (is_array($_value)) {
                trigger_error("update sql format error in setting value");
            }
            if (empty($this->_sqlTemplate['set'])) {
                $this->_sqlTemplate['set'] = "set $_key=?";
            } else {
                $this->_sqlTemplate['set'] .= ",$_key=?";
            }
            $this->parseBind($_value);
        }
        $this->parseFind($query);
    }
    
    protected function parseDelete($query) 
    {
        $tableStr = $this->_tableNames[0];
        $this->_sqlTemplate['delete'] = "DELETE FROM {$tableStr}";
        $this->parseFind($query);
    }
    
    /**
     * 
     * @param array $data
     * @param array $duplicateFields
     */
     protected function parseInsert($data, $duplicateFields = null, $isReplace = false)
     {
        if (!$isReplace) {
            $this->_sqlTemplate['insert'] = "INSERT into {$this->_tableNames[0]}(";
        } else {
            $this->_sqlTemplate['insert'] = "REPLACE into {$this->_tableNames[0]}(";
        }
        if (empty($data)) {
            trigger_error("insert data can't be empty", E_USER_ERROR);
        }
        $temp_arr = array();
        if ($this->_isBatchInsert) {
            $bind_values_arr = array();
            $bind_value_str = "";
            $insert_fields = array_keys($data[0]);
            $fields_str = implode(",", $insert_fields);
            $this->_sqlTemplate['insert'] .= $fields_str.")";
            
            // make bind values 
            $bind_template = "(";
            $temp_arr = array_pad($temp_arr, count($insert_fields), "?");
            $bind_template .= implode(",", $temp_arr).")";
            $bind_values_arr = array_pad($bind_values_arr, count($data), $bind_template);
            $bind_value_str = implode(",", $bind_values_arr);
            $this->_sqlTemplate['value'] = "values ".$bind_value_str;
            unset($bind_values_arr);
            unset($bind_value_str);
            
            $bind_values = array();
            foreach ($data as $_line => $_item) {
                $bind_values = array_merge($bind_values, array_values($_item));
            }
            $this->parseBind($bind_values);
            
            // parse duplicate
            if ($duplicateFields !== null) {
                trigger_error("duplicate is not surpported when you use batch insert", E_USER_WARNING);
            }
        } else {
            $insert_fields = array_keys($data);
            $fields_str = implode(",", $insert_fields);
            $this->_sqlTemplate['insert'] .= $fields_str.")";
            $temp_arr = array_pad($temp_arr, count($insert_fields), "?");
            $bind_template = "";
            $bind_template .= "(".implode(",", $temp_arr).")";
            $this->_sqlTemplate['value'] = "values ".$bind_template;
            
            // make bind values
            $temp_arr = array();
            $bind_values = array_values($data);
            $this->parseBind($bind_values);
            
            // parse duplicate
            if ($duplicateFields !== null) {
                if (!is_array($duplicateFields)) {
                    if (!in_array($duplicateFields, $insert_fields)) {
                        trigger_error("duplicate fields error", E_USER_ERROR);
                    }
                    $duplicateFields = array($duplicateFields);
                }
                $this->_sqlTemplate['value'] .= " ON DUPLICATE KEY UPDATE ";
                $bind_template_arr = array();
                foreach ($duplicateFields as $_field) {
                    $_value = $data[$_field];
                    $bind_template_arr[] = "$_field=?";
                    $this->parseBind($_value);
                }
                $this->_sqlTemplate['value'] .= implode(",", $bind_template_arr);
            }
            
        }
    }
    
    /**
     * query template, make correct order
     */
    protected function _parseSqlStr()
    {
        $_sqlTemplate = array();
        $query_type = $this->_sqlType;
        if (!empty($this->_sqlTemplate['top'])) {
            $fields = empty($this->_selectFields)?"*":implode(",", $this->_selectFields);
            $this->_sqlTemplate['select'] = "select ".$this->_sqlTemplate['top'].$fields;
        }
        if (!empty($this->_aggregationType) && !empty($this->_aggregationField)) {
            if (empty($this->_sqlTemplate['select'])) {
                $this->_sqlTemplate['select'] = "select {$this->_aggregationType}"."(".
                                                $this->_aggregationField.")";
            } else {
                if (!empty($this->_selectFields)) {
                    $this->_sqlTemplate['select'] .= ",{$this->_aggregationType}"."(".
                                                  $this->_aggregationField.") as {$this->_aggregationType}";
                } else {
                    $this->_sqlTemplate['select'] .= " {$this->_aggregationType}"."(".
                                                      $this->_aggregationField.")as {$this->_aggregationType}";
                }
            }
        }
        
        switch ($query_type) {
            case "find":
                if (!$this->_isNestedSelect) {
                    $_sqlTemplate = array(
                        $this->_sqlTemplate['select'],
                        $this->_sqlTemplate['from'],
                        $this->_sqlTemplate['join'],
                        $this->_sqlTemplate['where'],
                        $this->_sqlTemplate['group'],
                        $this->_sqlTemplate['having'],
                        $this->_sqlTemplate['order'],
                        $this->_sqlTemplate['limit'],
                    );
                } else {
                    $_sqlTemplate = array(
                        $this->_sqlTemplate['select'],
                        $this->_sqlTemplate['from'],
                        $this->_sqlTemplate['join'],
                        $this->_sqlTemplate['where'],
                        $this->_sqlTemplate['group'],
                        $this->_sqlTemplate['having'],
                        $this->_sqlTemplate['order'],
                        $this->_sqlTemplate['limit'],
                    );
                    $innerSqlStr = implode(" ", $_sqlTemplate);
                    $innerSqlStr = "(".$innerSqlStr.")";
                    $this->_sqlTemplate['outer_where'] = $this->_sqlTemplate['outer_where'].$innerSqlStr;
                    
                    $_sqlTemplate = array(
                        $this->_sqlTemplate['outter_function'],
                        $this->_sqlTemplate['outter_from'],
                        $this->_sqlTemplate['outter_where'],
                    );
                }
                break;
            case "insert":
                $_sqlTemplate = array(
                    $this->_sqlTemplate['insert'],
                    $this->_sqlTemplate['value'],
                    $this->_sqlTemplate['duplicate'],
                );
                break;
            case "batchInsert":
                $_sqlTemplate = array(
                    $this->_sqlTemplate['insert'],
                    $this->_sqlTemplate['value'],
                );
                break;
            case "update":
                $_sqlTemplate = array(
                    $this->_sqlTemplate['update'],
                    $this->_sqlTemplate['set'],
                    $this->_sqlTemplate['where'],
                    $this->_sqlTemplate['order'],
                    $this->_sqlTemplate['limit'],
                );
                break;
            case "delete":
                $_sqlTemplate = array(
                    $this->_sqlTemplate['delete'],
                    $this->_sqlTemplate['where'],
                    $this->_sqlTemplate['order'],
                    $this->_sqlTemplate['limit'],
                );
                break;
            default: trigger_error("unsupported sql query key", E_USER_ERROR);
        }
        $this->_sqlPrototype = implode(" ", array_filter(array_map('trim', $_sqlTemplate)));
    }
    
    /**
     * use pdo bind sql & execute
     * @return mixed, where you do find, it return array,or return int (affected rows)
     */
    public function execute($outer_sql = null)
    {
        if (empty($this->_db)) {
            $this->connect();
        }
        if ($outer_sql === null) {
            if (empty($this->_sqlType)) trigger_error('sql method is missing');
            $this->_parseSqlStr();
            if ($this->_debug) {
                /* debug */ 
                var_dump($this->_sqlPrototype, $this->_sqlTemplate, 
                         $this->_needToBindNames, $this->_bindParams);
            }
            $sth = $this->_db->prepare($this->_sqlPrototype);
            foreach ($this->_needToBindNames as $_bindName => $_bindType) {
                $_bindNum = (int)substr($_bindName, 1);
                $sth->bindValue($_bindNum, $this->_bindParams[$_bindName], $_bindType);
            }
            
            // reset sql settings
            $this->_sqlTemplate = array();
            foreach (self::$_commonQueryKeys as $_query) {
                $this->_sqlTemplate[$_query] = "";
            }
            $this->_needToBindNames = array();
            $this->_bindParams = array();
            $this->_sqlPrototype = null;
            // !reset sql settings
            
            if ($sth->execute()) {
                if ($this->_sqlType == "find" && empty($this->_aggregationField)) {
                    if ($this->_isToFindOne) {
                        return $sth->fetch(PDO::FETCH_ASSOC);
                    } else {
                        return $sth->fetchAll(PDO::FETCH_ASSOC);
                    }
                } else if ($this->_sqlType == "find" && !empty($this->_aggregationField) 
                           && !empty($this->_aggregationType)) {
                    $res = $sth->fetch(PDO::FETCH_ASSOC);
                    return  intval($res[$this->_aggregationType]);
                } else {
                    return $sth->rowCount();
                }
            } else {
                $errarr = $sth->errorInfo();
                trigger_error("sql execute error:{$errarr[2]}", E_USER_ERROR);
            }
        } else {
            return $this->_db->exec($outer_sql);  
        }
    }

    protected function configurable()
    {
        return array(
            'db',
            'error',
            'server',
            'port',
            'dbName',
            'charset',
            'driverOptions',
            'userName',
            'password',
            'debug',
        );
    }
}
