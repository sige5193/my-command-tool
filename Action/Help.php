<?php
namespace Action;
use Core\CommandActionAbstract;
use Core\Util as CoreUtil;
use Core\OhaCore;
class Help extends CommandActionAbstract {
    /**
     * 显示帮助信息
     * @param string $name 命令名称
     * @return void
     */
    public function run ( $name=null ) {
        (null===$name) ? $this->showAll() : $this->showDetail($name);
    }
    
    /** @return void */
    private function showAll() {
        $basePath = OhaCore::system()->getPath('Action');
        $files = $this->fetchDir($basePath);
        CoreUtil::printf("Commands : \n");
        $commands = array();
        $maxCommandLength = 0;
        foreach ( $files as $file ) {
            $action = str_replace(array($basePath,'.php'), '', $file);
            $action = '\\Action'.str_replace(DIRECTORY_SEPARATOR, '\\', $action);
            if ( !class_exists($action) ) {
                continue;
            }
            
            $actionInfo = new \ReflectionClass($action);
            $handler = $actionInfo->getMethod('run');
            $docComment = $handler->getDocComment();
            $description = $this->getActionNameFromDocComment($docComment);
            $command = $this->getActionCommandByClassName($action);
            $commands[] = array('name'=>$command, 'description'=>$description);
            
            if ( strlen($command) > $maxCommandLength ) {
                $maxCommandLength = strlen($command);
            }
        }
        
        foreach ( $commands as $command ) {
            CoreUtil::printf("    %-{$maxCommandLength}s    %s\n", $command['name'], $command['description']);
        }
    }
    
    /** @return string */
    private function getActionCommandByClassName($action) {
        $action = str_replace('\\Action\\', '', $action);
        $action = implode('/', array_map('lcfirst', explode('\\', $action)));
        $action = preg_replace('#([A-Z])#', '-${1}', $action);
        $action = implode('-', array_map('lcfirst', explode('-', $action)));
        return $action;
    }
    
    /** @return array */
    private function fetchDir( $path ) {
        if ( is_file($path) ) {
            return array($path);
        }
        
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $files = array();
        $subFiles = scandir($path);
        foreach ( $subFiles as $subFile ) {
            if ( '.' === $subFile[0] ) {
                continue;
            }
            $subPath = $path.DIRECTORY_SEPARATOR.$subFile;
            if ( is_dir($subPath) ) {
                $files = array_merge($files, $this->fetchDir($subPath));
            } else {
                $files[] = $subPath;
            }
        }
        return $files;
    }
    
    /**
     * @param string $name
     * @return void
     */
    private function showDetail( $name ) {
        $actionClass = explode('/', $name);
        $actionClass = array_map(array(CoreUtil::class, 'strMiddleSnakeToUcfirstCamel'), $actionClass);
        $actionClass = '\\Action\\'.implode('\\', $actionClass);
        
        $actionInfo = new \ReflectionClass($actionClass);
        $handler = $actionInfo->getMethod('run');
        
        $docComment = $handler->getDocComment();
        CoreUtil::printf("\n");
        CoreUtil::printf("Name : %s\n", $this->getActionNameFromDocComment($docComment));
        CoreUtil::printf("Commandd : %s\n", $name);
        CoreUtil::printf("Parameters : \n");
        
        $params = $this->getParamInfoFromDocComment($docComment);
        $params[] = array('name'=>'setup','type'=>'string', 'comment'=>'配置文件');
        foreach ( $params as $param ) {
            CoreUtil::printf("    %s %s %s\n", $param['type'], $param['name'], $param['comment']);
        }
    }
    
    /**
     * @param string $comment
     * @return array
     */
    private function getParamInfoFromDocComment( $comment ) {
        $document = explode("\n", $comment);
        array_shift($document);
        array_pop($document);
        
        $params = array();
        foreach ( $document as $index => $line ) {
            $line = trim($line);
            $line = trim($line, '*');
            preg_match('#@param (.*?) \\$(.*?) (.*)#is', $line, $match);
            
            if ( empty($match) ) {
                continue;
            }
            $params[] = array(
                'name' => $match[2],
                'type' => $match[1],
                'comment' => $match[3],
            );
        }
        return $params;
    }
    
    /**
     * @param string $document
     * @return string
     */
    private function getActionNameFromDocComment( $comment ) {
        $document = explode("\n", $comment);
        $name = trim($document[1]);
        $name = trim($name, '*');
        return $name;
    }
}