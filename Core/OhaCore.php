<?php
namespace Core;
class OhaCore {
    /** @var OhaCore */
    private static $core = null;
    
    /** @return self */
    public static function system() {
        if ( null === self::$core ) {
            self::$core = new OhaCore();
        }
        return self::$core;
    }
    
    /** @var array */
    private $config = array();
    
    /** @return array */
    public function getConfig() {
        return $this->config;
    }
    
    /** @return void */
    private function __construct() {
        spl_autoload_register(array($this, '_autoloader'));
        $this->config = require $this->getPath('Configuration/Main.php');
        date_default_timezone_set($this->config['Core']['TimeZone']);
        $this->registerLibraryAutoloader();
    }
    
    /** @return void */
    private function registerLibraryAutoloader() {
        $path = $this->getPath('Lib');
        $libs = scandir($path);
        foreach ( $libs as $lib ) {
            if ( '.' === $lib[0] ) {
                continue;
            }
            $autoloader = "{$path}/{$lib}/autoloader.php";
            if ( file_exists($autoloader) ) {
                require $autoloader;
            }
        }
    }
    
    /** @return void */
    public function run() {
        $argv = Util::getCommandArgs();
        if ( !isset($argv[1]) ) {
            $argv[1] = 'help';
        }
        $action = $argv[1];
        
        array_shift($argv);
        array_shift($argv);
        
        $actionClass = explode('/', $action);
        $actionClass = array_map(array(Util::class, 'strMiddleSnakeToUcfirstCamel'), $actionClass);
        $actionClass = '\\Action\\'.implode('\\', $actionClass);
        
        $actionObject = new $actionClass($action);
        $actionObject->execute($argv);
    }
    
    /** @return void */
    public function _autoloader( $class ) {
        $classPath = str_replace('\\', '/', $class);
        $filepath = $this->getPath($classPath.'.php');
        if ( is_file($filepath) ) {
            require $filepath;
        }
    }
    
    /** @return string */
    public function getPath( $path, $separator=DIRECTORY_SEPARATOR ) {
        $basepath = str_replace(DIRECTORY_SEPARATOR, $separator, dirname(dirname(__FILE__)));
        return $basepath.$separator.str_replace('/', $separator, $path);
    }
}