<?php
namespace Util;
use Core\OhaCore;
class CommandActionInfo {
    /** @return array */
    public static function findAllCommandClasses() {
        $actionPath = OhaCore::system()->getPath('Action');
        $actionFiles = FileSystem::fetchDirFiles($actionPath);
        
        $commands = array();
        foreach ( $actionFiles as $file ) {
            $action = str_replace(array($actionPath,'.php'), '', $file);
            $action = '\\Action'.str_replace(DIRECTORY_SEPARATOR, '\\', $action);
            if ( !class_exists($action) ) {
                continue;
            }
            $commands[] = $action;
        }
        return $commands;
    }
    
    /**
     * @param string $className
     * @return \Util\CommandActionInfo 
     */
    public static function getInfoByClass( $className ) {
        return new self($className);
    }
    
    /** @var string 命令行Action类名称 */
    private $className = null;
    
    /** @param string 命令行Action类名称 */
    private function __construct( $className ) {
        $this->className = $className;
    }
    
    /**
     * 获取命令行名称
     * @return string
     */
    public function getName() {
        $action = str_replace('\\Action\\', '', $this->className);
        $action = implode('/', array_map('lcfirst', explode('\\', $action)));
        $action = preg_replace('#([A-Z])#', '-${1}', $action);
        $action = implode('-', array_map('lcfirst', explode('-', $action)));
        return $action;
    }
    
    /**
     * 获取命令行描述
     * @return string
     */
    public function getDescription() {
        $actionInfo = new \ReflectionClass($this->className);
        $handler = $actionInfo->getMethod('run');
        $docComment = $handler->getDocComment();
        
        $docComment = explode("\n", $docComment);
        $name = trim($docComment[1]);
        $name = trim($name, '*');
        return $name;
    }
}