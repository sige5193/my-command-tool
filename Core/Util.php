<?php
namespace Core;
class Util {
    /**
     * @param string $string
     * @return string
     */
    public static function strMiddleSnakeToLcfirstCamel( $string ) {
        $string = explode('-', $string);
        $string = array_map('ucfirst', $string);
        $string = implode('', $string);
        $string = lcfirst($string);
        return $string;
    }
    
    /**
     * @param string $string
     * @return string
     */
    public static function strMiddleSnakeToUcfirstCamel( $string ) {
        return ucfirst(self::strMiddleSnakeToLcfirstCamel($string));
    }
    
    /**
     * @return array
     */
    public static function getCommandArgs() {
        global $argv;
        
        $params = array();
        foreach ( $argv as $arg ) {
            $params[] = iconv("GB2312", "UTF-8", $arg);
        }
        return $params;
    }
    
    /** @param string $content */
    public static function printf( $content ) {
        $message = call_user_func_array('sprintf', func_get_args());
        $message = iconv("UTF-8", "GB2312//IGNORE", $message);
        echo $message;
    }
}