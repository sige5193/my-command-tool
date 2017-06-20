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
        
        $config = OhaCore::system()->getConfig();
        $params = array();
        foreach ( $argv as $arg ) {
            $params[] = iconv($config['CharSet']['Input'], "UTF-8", $arg);
        }
        return $params;
    }
    
    /** @param string $content */
    public static function printf( $content ) {
        $message = call_user_func_array('sprintf', func_get_args());
        $config = OhaCore::system()->getConfig();
        $message = iconv("UTF-8", $config['CharSet']['Output'], $message);
        echo $message;
    }
}