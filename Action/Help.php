<?php
namespace Action;
use Core\CommandActionAbstract;
use Core\Util as CoreUtil;
class Help extends CommandActionAbstract {
    /**
     * @param string $name 命令名称
     * @return void
     */
    public function run ( $name=null ) {
        $this->showDetail($name);
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