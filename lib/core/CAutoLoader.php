<?php
/**
 * 自动加载config、include、lib下面的文件
 * @author chuanbin<hcb0825@126.com>
 * @since 2012-08
 */
class CAutoLoader {
    
    protected static $_classIncPathMap = array();
    protected static $_settedIncPath = array();
    protected static $_loadedClassMap = array();
    public static function libCoreLoader($className) 
    {
        $file = $className.".php";
        if (!isset(self::$_classIncPathMap[$className])){
            $libCoreRoot = CApp::getApp()->systemLibCoreDir;
            if (!isset(self::$_settedIncPath[$className])) {
                $newIncPath =  get_include_path().PATH_SEPARATOR.$libCoreRoot;
                if (!isset(self::$_settedIncPath[$libCoreRoot])) {
                    set_include_path($newIncPath);
                    self::$_settedIncPath[$libCoreRoot] = true;
                    //echo "setted core inc path:".$userLibDir." for class $className\n";
                }
            }
            if (self::filexists($file) !== FALSE) {
                self::$_classIncPathMap[$className] = $libCoreRoot;
                self::fileRequireOnce($className);
            }
        } else {
            self::fileRequireOnce($className);
        }
    }
    
    public static function libModuleLoader($className) 
    {
        $file = $className.".php";
        $libModuleRoot = CApp::getApp()->systemLibModuleDir;
        if (!isset(self::$_classIncPathMap[$className])){
            if (!isset(self::$_settedIncPath[$libModuleRoot])) {
                $newIncPath =  get_include_path().PATH_SEPARATOR.$libModuleRoot;
                if (!isset(self::$_settedIncPath[$libModuleRoot])) {
                    set_include_path($newIncPath);
                    self::$_settedIncPath[$libModuleRoot] = true;
                    //echo "setted core inc path:".$userLibDir." for class $className\n";
                }
            }
            if (self::filexists($file) !== FALSE) {
                self::$_classIncPathMap[$className] = $libModuleRoot;
                self::fileRequireOnce($className);
            }
        } else {
            self::fileRequireOnce($className);
        }
    }
    
    public static function includeLoader($className)
    {
        $file = $className.".php";
        if (!isset(self::$_classIncPathMap[$className])){
            $userLibDir = CApp::getApp()->userLibDir;
            if (!isset(self::$_settedIncPath[$userLibDir])) {
                $newIncPath =  get_include_path().PATH_SEPARATOR.$userLibDir;
                if (!isset(self::$_settedIncPath[$userLibDir])) {
                    set_include_path($newIncPath);
                    self::$_settedIncPath[$userLibDir] = true;
                }
            }
            if (self::filexists($file) !== FALSE) {
                self::$_classIncPathMap[$className] = $userLibDir;
                self::fileRequireOnce($className);
            }
        } else {
            self::fileRequireOnce($className);
        }
    }
    
    public static function getAppInstance()
    {
        return CApp::getApp();
    }
    
    public static  function loadClass($className)
    {
        $file = $className.".php";
        if (!isset( self::$_classIncPathMap[$className])) {
            $coreRoot = dirname(__FILE__);
            if (!isset(self::$_settedIncPath[$coreRoot])) {
                $newIncPath = get_include_path().PATH_SEPARATOR.$coreRoot;
                if (!isset(self::$_settedIncPath[$coreRoot])) {
                    set_include_path($newIncPath);
                    self::$_settedIncPath[$coreRoot] = true;
                    echo "setted core inc path:".$coreRoot." for class $className\n";
                }
            }
            if (self::filexists($file) !== FALSE) {
                self::$_classIncPathMap[$className] = $coreRoot;
                self::fileRequireOnce($className);
            } else {
                self::libModuleLoader($className);
                self::includeLoader($className);
            }
        } else {
            self::fileRequireOnce($className);
        }
    }
    
    public static function filexists($file)
    {
        $ps = explode(":", get_include_path());
        if(file_exists($file)) return true;
        foreach($ps as $path)
        {
            if(file_exists($path.DIRECTORY_SEPARATOR.$file)) return true;
        }
        return false;
    }
    
    public static function fileRequireOnce($className)
    {
        $file = $className.".php";
        if (!isset(self::$_loadedClassMap[$className])) {
            self::$_loadedClassMap[$className] = true;
            require $file;
        }
    }
}