<?php
/**
 * utils & tools 
 * @author chuanbin<hcb0825@126.com>
 * @since 2012-09
 */
class CUtils {
    /**
     * memcache counter
     * @param string mc_host
     * @param string mc_port
     * @param string counter_key 计数器key
     */
    static function mc_counter_incre($mc_host, $mc_port, $counter_key)
    {
        $memcache = new Memcached;
        $memcache->addServer($mc_host, $mc_port);
        if (!$memcache->add($counter_key, 0)) {
            $memcache->increment($counter_key);
        } else {
            $memcache->set($counter_key, 1);
        }
        unset($memcache);
    }
    
    /**
     * get value from memcache key
     * @param string $mc_host
     * @param int $mc_port
     * @param sting $get_key
     */
    static function mc_get($mc_host, $mc_port, $get_key)
    {
        global $_SC;
        $need_compression = isset($_SC['proxy_pool_mc']['need_compression'])?$_SC['proxy_pool_mc']['need_compression']:null;
        $serializer = isset($_SC['proxy_pool_mc']['memcache_serializer'])?$_SC['proxy_pool_mc']['memcache_serializer']:null;
        
        $memcache = new Memcached;
        $memcache->addServer($mc_host, $mc_port);
        if ($need_compression !== null) {
            $memcache->setOption(Memcached::OPT_COMPRESSION, $need_compression);
        }
        if ($serializer !== null) {
            $memcache->setOption(Memcached::OPT_SERIALIZER, $serializer);
        }
        return $memcache->get($get_key);
    }
    
    /**
     * set memcache key value
     * @param string $mc_host
     * @param int $mc_port
     * @param string $set_key
     * @param mixed $set_value
     */
    static function mc_set($mc_host, $mc_port, $set_key, $set_value)
    {
        global $_SC;
        $need_compression = isset($_SC['proxy_pool_mc']['need_compression'])?$_SC['proxy_pool_mc']['need_compression']:null;
        $serializer = isset($_SC['proxy_pool_mc']['memcache_serializer'])?$_SC['proxy_pool_mc']['memcache_serializer']:null;
        
    //    var_dump($_SC);
    //    exit();
        $memcache = new Memcached;
        $memcache->addServer($mc_host, $mc_port);
        if ($need_compression !== null) {
            $memcache->setOption(Memcached::OPT_COMPRESSION, $need_compression);
        }
        if ($serializer !== null) {
            $memcache->setOption(Memcached::OPT_SERIALIZER, $serializer);
        }
        $memcache->set($set_key, $set_value);
    }
    
    /**
     * 并发curl抓取结果
     * 非阻塞方式的curl multi请求，解决阻塞方式是CPU占用率过高的问题
     * 并且一旦有一个curl请求有结果后处理完立马释放curl连接,可以解决并发连接数过高的问题
     * @param array('url1','url2'......) $nodes
     * @param $curl_connect_timeout
     */
    static function multiple_threads_request_rolling($nodes, $curl_connect_timeout){ 
        global $_SCONFIG;
        $curl_array = array(); 
        $mh = curl_multi_init();
        $return_res = array(); 
        foreach($nodes as $i => $url) 
        { 
            if (empty($url)) {
                continue;
            }
            $curl_array[$i] = curl_init($url); 
            curl_setopt($curl_array[$i], CURLOPT_HEADER, false);
            curl_setopt($curl_array[$i], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_array[$i], CURLOPT_CONNECTTIMEOUT, $curl_connect_timeout);
            curl_multi_add_handle($mh, $curl_array[$i]); 
        } 
        $running = null; 
        do { 
            $status_cme = curl_multi_exec($mh,$running); 
    //        usleep(1000);
        } while($status_cme == CURLM_CALL_MULTI_PERFORM); 
        do {
            while (($status_cme = curl_multi_exec($mh,$running))== CURLM_CALL_MULTI_PERFORM);
            if ($status_cme != CURLM_OK) { break; }
            //获取已经有正常结果返回的数据
            while ($done = curl_multi_info_read($mh)) {
                $handler = $done['handle'];
                $ok_res = curl_multi_getcontent($handler);
                $ok_node_number = -1;
                foreach ($curl_array as $_k => $_handler) {
                    if ($_handler === $handler) {
                        $ok_node_number = $_k;
                        break;
                    }
                }
                $ok_url = $nodes[$ok_node_number];
                $return_res[$ok_url] = $ok_res;
                curl_multi_remove_handle($mh, $handler); 
                curl_close($handler);
            }
            if ($running) {
                curl_multi_select($mh, $_SCONFIG['curl_multi_select_time']);
            }
        } while ($running);
        curl_multi_close($mh);
        return $return_res; 
    }

    /**
     * 对数组里每个维度的值进行转码
     * @param string $in_charactor
     * @param string $out_charactor
     * @param array $data_arr
     */
    static function my_icov($in_charset, $out_charset, $data_arr, $option = null) {
        $returnarr = array();
        if (!is_array($data_arr)) {
            echo "parameter 3 need to be array";
            return NULL;
        }
        foreach ($data_arr as $_k => $_v) {
            $valid_inchar = (@iconv($in_charset, $in_charset, $_v) === $_v);
            if ($valid_inchar) {
                switch ($option) {
                    case "IGNORE" : $tmp = iconv($in_charset, $out_charset."//IGNORE", $_v);
                                    break;
                    case "TRANSLIT": $tmp = iconv($in_charset, $out_charset."//TRANSLIT", $_v);
                    break;
                    default:$tmp = iconv($in_charset, $out_charset, $_v);break;
                }
                
                if ($tmp !== false) {
                    $returnarr[$_k] = $tmp;
                }else {
                    $returnarr[$_k] = $_v;
                }
            } else {
                loger::log("source charset is wrong", "INFO");
                $returnarr[$_k] = $_v;
            }
        }
        return $returnarr;
    }

    /**
     * @param array $src_data
     * @param array(nodename=> array(attr_name, attr_value),.....) 节点属性
     * @param array(nodename)
     * @param string $doc_root
     * @param string $output_encoding
     * @return string
     */
    static function simplexml_array2xml($src_data, $output_encoding = "UTF-8", $doc_root = "entity", $attributes = null, $cdatas = null){
        $tpl_xml_str = <<<XML
<?xml version='1.0' encoding='UTF-8'?>\n
<$doc_root></$doc_root>
XML;
//    echo $tpl_xml_str;
    $xml_element_obj = simplexml_load_string($tpl_xml_str);
    if ($xml_element_obj === false) {
        $errors = libxml_get_errors();
    }
//    var_dump($xml_element_obj);
    simplexml_obj2xml($xml_element_obj, $src_data, $attributes, $cdatas);
    return $xml_element_obj->saveXML();
    }

    /**
     * 递归把对象生成xml
     * @param Object $xml_element_obj
     * @param array
     * @param array(nodename=> array(attr_name, attr_value),.....) 节点属性 $node_data
     * @param array(nodename,...) 知道哪些节点的值是cdata节点
     */
    static function simplexml_obj2xml(&$xml_element_obj, $node_data, $attributes = null, $cdatas = null) {
        foreach ($node_data as $_k => $_data) {
            if (!is_array($_data)) {
                $text = $_data;
                if ($cdatas !== null) {
                    if (in_array($_k, $cdatas)) {
                        $text = "<![CDATA[".$_data."]]>";
                    }
                }
                $sub_node_obj = $xml_element_obj->addChild($_k, $text);
                if ($attributes !== null) {
                    if (array_key_exists($_k, $attributes)) {
                        $attr_name = $attributes[$_k][0];
                        $attr_val = $attributes[$_k][1];
                        echo "attr:$attr_name,value:$attr_val</br>";
                        $sub_node_obj->addAttribute($attr_name, $attr_val);
                    }
                }
            } else {
                $sub_node_obj = $xml_element_obj->addChild($_k);
                if ($attributes !== null) {
                    if (array_key_exists($_k, $attributes)) {
                        $attr_name = $attributes[$_k][0];
                        $attr_val = $attributes[$_k][1];
                        echo "attr:$attr_name,value:$attr_val</br>";
                        $sub_node_obj->addAttribute($attr_name, $attr_val);
                    }
                }
                simplexml_obj2xml($sub_node_obj, $_data, $attributes, $cdatas);
            }
        }
    }
    
    /**
     * is cli usage
     * @return bool
     */
    static function is_cli() {
        return strtolower(substr(php_sapi_name(), 0, 3)) == 'cli';
    }
    /**
     * 分页函数
     * @param int $num 总行数
     * @param int $curpage
     * @param string $mpurl
     * @param $options=array(page_size,show_first_last,show_next_prev,first_page_text,last_page_text,
     *                        prev_page_text,next_page_text,param_name)
     * @return string
     * @author zhongjiuzhou@dangdang.com
     */
    static function pagenav($num, $curpage, $mpurl, $options=array())
    {
        global $_SCONFIG, $_SGLOBAL;
        
        $defaults = array(
            'page_size'=>10,
            'prev_page_text'=>'上一页',
            'next_page_text'=>'下一页',
            'param_name'=>'page',
            'display_numbers'=>5,
            'pre_display_numbers'=>2,
            'post_display_numbers'=>2,
            'class_name'=>'fanye',
            'active_class_name'=>'nonce',
            'prev_page_class_name'=>'fanye_page',
            'next_page_class_name'=>'fanye_page',
            'dot_class_name'=>'dot'
        );
        $options = array_merge($defaults, $options);
        if ($options['display_numbers'] % 2 == 0) {
            $options['display_numbers'] += 1;
        }
        
        $multipage = '';
        $mpurl .= strpos($mpurl, '?') ? (substr($mpurl,-1)!='?' ? '&':'') : '?';
        
        $realpages = @ceil($num / $options['page_size']);
        $html = '<div class="' . $options['class_name'] . '">';
        if ($realpages > $options['display_numbers']) {
            $number_per_side = floor($options['display_numbers'] / 2);
            $start_number = $curpage - $number_per_side;
            $end_number = $curpage + $number_per_side;
            if ($start_number < 1) {
                $end_number += abs($start_number - 1);
                $start_number = 1;
            }
            if ($end_number > $num) {
                $start_number -= ($end_number - $num);
                $end_number = $num;
            }
            if ($start_number - 1 > $options['pre_display_numbers']) {
                $pre_numbers = array(1, $options['pre_display_numbers']);
            } else {
                $start_number = 1;
            }
            if ($end_number < ($realpages - $options['post_display_numbers'])) {
                $post_numbers = array($realpages - $options['post_display_numbers'] + 1, $realpages);
            } else {
                $end_number = $realpages;
            }
            
            if ($start_number > $curpage) {
                $start_number = $curpage;
            }
            if ($end_number < $curpage) {
                $end_number = $curpage;
            }
        } else {
            $start_number = 1;
            $end_number = $realpages;
        }
    
        if ($curpage < $realpages) {
            $html .= '<a class="' . $options['next_page_class_name'] . '" href="' . $mpurl . 'page=' . ($curpage + 1) . '"><span>' .
                     $options['next_page_text'] . '</span></a>';
        }
        
        if (isset($post_numbers)) {
            for ($i=$post_numbers[1]; $i>=$post_numbers[0]; --$i) {
                $html .= '<a href="' . $mpurl . 'page=' . $i . '"><span>' . $i . '</span></a>';
            }
            $html .= '<span class="' . $options['dot_class_name'] . '">...</span>';
        }
        for ($i=$end_number; $i>=$start_number; --$i) {
            $extra = '';
            if ($i == $curpage) {
                $extra = ' class="' . $options['active_class_name'] . '"';
            }
            $html .= '<a' . $extra . ' href="' . $mpurl . 'page=' . $i . '"><span>' . $i . '</span></a>';
        }
        if (isset($pre_numbers)) {
            $html .= '<span class="' . $options['dot_class_name'] . '">...</span>';
            for ($i=$pre_numbers[1]; $i>=$pre_numbers[0]; --$i) {
                $html .= '<a href="' . $mpurl . 'page=' . $i . '"><span>' . $i . '</span></a>';
            }
        }
        
        if ($curpage > 1) {
            $html .= '<a class="' . $options['prev_page_class_name'] . '" href="' . $mpurl . 'page=' . ($curpage - 1) . '"><span>' .
                    $options['prev_page_text'] . '</span></a>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * 发起一个HTTP请求
     * @param string $url
     * @param mixed $post_data 可以关联数组，也可以直接是经过URL编码后字符串
     * @param array $headers http请求头信息, 格式为KEY=>VALUE形式
     * @param int $timeout 超时，0为不限制
     * @param bool $follow_loc 是否跟踪Location跳转
     * @param bool $output_header 是否输出HTTP头信息
     * @param bool $halt 遇到错误是否exit
     * @example 
     * @return string
     */
    //function shttp_request($url, $post_data=array(), $headers=array(), $timeout=3, $follow_loc=0, $output_header=0, $halt=1)
    static function shttp_request($url, $options = array(), &$request_errmsg = null)
    {
        //记录debuginfo
        $debug_time_start = microtime(1);
            
        //默认配置
        $default_options = array(
                'post_data' => array(), //可以关联数组，也可以直接是经过URL编码后字符串
                'headers' => array(), //http请求头信息, 格式为KEY=>VALUE形式
                'timeout' => 3, //sec, 超时，0为不限制
                'follow_loc' => 0, //是否跟踪Location跳转
                'output_header' => 0, //是否输出HTTP头信息
                'userpwd' => array(), //用户名和密码，需要验证时使用。格式：array('username', 'password')
                'maxredirs' => 5, // 最大跳转次数
                'halt' => 1, //遇到错误是否exit
            );
        $options = array_merge($default_options, $options);
        $ch = curl_init();
        //url
        curl_setopt($ch, CURLOPT_URL, $url);
        
        //instead of outputting it out directly
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //automatically set the Referer
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        //TRUE to follow any "Location: " header that the server sends
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $options['follow_loc'] ? true : false);    
        //maximum amount of HTTP redirections to follow
        curl_setopt($ch, CURLOPT_MAXREDIRS, $options['maxredirs']);
        //The number of seconds to wait whilst trying to connect. Use 0 to wait indefinitely
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $options['timeout']);
        
        if( !empty($options['headers']) ) {
            $header_user_agent = 0;//is set user agent
            foreach ($options['headers'] as $hkey=>$hval) {
                if(strtolower(trim($hkey)) == 'user-agent') { $header_user_agent = 1; }
                $nheaders[] = trim($hkey).": ".trim($hval);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $nheaders);
        }
        //Set Default User-Agent
        if( empty($header_user_agent) ) {
            //IE7 on Windows Xp
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)');
        }
        //TRUE to include the header in the output
        curl_setopt($ch, CURLOPT_HEADER, $options['output_header'] ? true : false);
        
        //HTTPS
        if( stripos($url, "https://") !== FALSE ) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        
        //Set Username & Password
        if( !empty($options['userpwd']) ) {
            curl_setopt($ch, CURLOPT_USERPWD, "[{$options['username']}]:[{$options['password']}]");
        }
        
        //post data
        if( !empty($options['post_data']) ) {
            curl_setopt($ch, CURLOPT_POST, true);
            if( is_array($options['post_data']) )
            {
                $encoded = "";
                foreach ( $options['post_data'] as $k=>$v)
                {   
                    $encoded .= "&".rawurlencode($k)."=".rawurlencode($v);
                }
                $encoded = substr($encoded, 1);//去掉首个'&'
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
            }else{
                curl_setopt($ch, CURLOPT_POSTFIELDS, $options['post_data']);
            }
        }
        
        $res = curl_exec($ch);
        
        if( $res === FALSE ) {
    //        header("HTTP/1.0 500 Internal Server Error" , true , 500);
            $request_errmsg = "[function shttp_request]REQUEST URL: {$url}，FAILURE! Error: ".curl_error($ch)."\n";
            if($options['halt']) {
                curl_close($ch);
                exit();
            }else{
                return FALSE;
            }
        }
        curl_close($ch);
        return $res; 
    }

    /**
     * @desc:binary search file and the length of every line is fixed 
     * @params
     *     needle:to search content
           search_file:file path 
           row_leng:fixed length of file line
           searchFp:file point 
     */
    static function bin_search_file($needle, $search_file, $row_leng, &$searchFp)
    {/*{{{*/
        if (!file_exists($search_file)) {
            trigger_error("can't find unsafe customer data file", "NOTICE");
        }
        if ($searchFp === null) {
            $fp = fopen($search_file, "rb");
        } else {
            $fp = &$searchFp;
        }
        // calc line counts
        fseek($fp, 0, SEEK_END);
        $end_off_set = ftell($fp);
        $total_line = $end_off_set / $row_leng;
//        var_dump($total_line);
//        exit();
        // start node position
        $start = 0;
        // end node position
        $end = $total_line - 1;
        $isFinded = false;
        while ($start <= $end) {
            // mid node position
            $mid = intval(($end + $start) / 2);
            fseek($fp, $mid * $row_leng);
            $mid_value = fread($fp, $row_leng);
            $tmp = rtrim($mid_value, "\n ");
//            var_dump($mid_value);
//            exit();
            if($needle == $tmp) {
                unset($search_file);
                unset($row_leng);
                $isFinded = true;
                fclose($fp);
                return $tmp;
            }
            if($tmp > $needle) {
                $end = $mid - 1;
            } else {
                $start = $mid + 1;
            }
            unset($tmp);
        }
        if (!$isFinded) {
            fclose($fp);
        }
        return false;
    }/*}}}*/

    /**
     * @param
     *     str:source string
     *     interval:hash region
     *     sub_len:pre-sub length of md5() hash value
     * @return:null or array()
     */
    static function str2numhash($str, $sub_len = 10, $interval = 10) {/*{{{*/
        if (empty($str)) {return null;}
        // 取前10位md5 hash值作为16进制，然后转成10进制，最后转成浮点数
	    // 范围是0至1099511627775,使用
	    //$hash_val = floatval(base_convert(substr(md5($str), 0, $sub_len), 16, 10)); 
	    $hash_val = base_convert(substr(md5($str), 0, $sub_len), 16, 10); 
        // 2^63
        $max_val = 9223372036854775808;
        if ($hash_val > $max_val) { 
            throw new Exception('number overflow');
	    }
        $region_num = $hash_val%$interval;
	    return array($hash_val, $region_num);
    }/*}}}*/
    
    static function send_email($message, $conf) {/*{{{*/
        if (empty($conf['name']) || empty($conf['from']) || empty($conf['to'])) {
            throw new Exception('parameter error for send_email function'); 
        }
        $name = $conf['name'];
        $from = $conf['from'];
        $to   = $conf['to'];
        $subject = $conf['subject'];
        $headers = sprintf("From: %s <%s>\r\n", $name, $from);
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "Content-Transfer-Encoding:8bit";
        mail($to, $subject, $message, $headers); 
    }/*}}}*/

    static function pullHdfsData($hdfs_path, $local_file, $is_zip = true, 
        $need_backup = true, $hdp_bin = '/home/work/software/hadoop/bin/hadoop') {/*{{{*/
        if (file_exists($local_file)) {
            if ($need_backup) {
                $new_file = $local_file .".bak"; 
                rename($local_file, $new_file);
            } else {
                unlink($local_file); 
            }
        }
        $cmd = sprintf('%s fs -getmerge %s %s', 
            $hdp_bin, $hdfs_path, $local_file); 
        if ($is_zip) {
            $cmd = sprintf('%s fs -decompress %s > %s',
                $hdp_bin, $hdfs_path, $local_file); 
        }
        $output = array();
        $stat = 0;
        $rst = exec($cmd, $output, $stat);
        if ($stat == 0) {
            return true;  
        } else {
            $msg = implode("\n", $output);
            return array(false, $msg);
        }
    }/*}}}*/

}
