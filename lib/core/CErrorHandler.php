<?php
class CErrorHandler {
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }
        $error_type = "";
        switch ($errno) {
            case E_WARNING :
                $error_type = "PHP RUN TIME WARNNING";
                break;
            case E_NOTICE :
                $error_type = "PHP RUN TIME NOTICE";
                break;
            case E_USER_ERROR :
                $error_type = "USER THROWED ERROR";
                break;
            case E_USER_NOTICE :
                $error_type = "USER THROWED NOTICE";
                break;
            case E_USER_WARNING :
                $error_type = "USER THROWED WARNING";
                break;
            case E_RECOVERABLE_ERROR:
                $error_type = "CATCHABLE PHP FATAL ERROR";
                break;
            default: 
                $error_type = "UNCAUTABLE ERROR";
                break;
        }
        $message = sprintf("%s: with message \"%s\" in file %s on line %s", $error_type, 
                           $errstr, $errfile, $errline);
        $extname = pathinfo($errfile, PATHINFO_EXTENSION);
        $basename = pathinfo($errfile, PATHINFO_BASENAME);
        $ext_len = strlen($extname);
        $basename_len = strlen($basename);
        $log_pre_len = $basename_len-$extname-1;
        $log_file_pre = substr($errfile, 0, $log_pre_len);
        $tmp = explode(" ", $error_type);
        $log_type = strtolower(array_pop($tmp));
        CLogger::defaultLog($message, $log_type);
        return true;
    }
    
    public static function exceptionHandler($exception) 
    {
        $message = sprintf("caught exception '%s' ".
                       "with message \"%s\" in %s on line %s.", 
                       get_class($exception), $exception->getMessage(), 
                       $exception->getFile(), $exception->getLine());
        CLogger::defaultLog($message, 'exception');
        return true;
    }
}

