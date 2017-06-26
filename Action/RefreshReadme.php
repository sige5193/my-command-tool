<?php
namespace Action;
use Core\CommandActionAbstract;
use Util\CommandActionInfo;
use Core\OhaCore;
class RefreshReadme extends CommandActionAbstract {
    /**
     * 根据命令更新Readme.md文件
     * @return void
     */
    public function run ( ) {
        $actionClasses = CommandActionInfo::findAllCommandClasses();
        
        $commands = array();
        foreach ( $actionClasses as $index => $actionClass ) {
            $action = CommandActionInfo::getInfoByClass($actionClass);
            $commands[] = array(
                'name' => $action->getName(),
                'description' => $action->getDescription(),
            );
        }
        $content = $this->renderView('Data/Template/ProjectReadme.php', array('commands'=>$commands));
        
        $readmePath = OhaCore::system()->getPath('README.md');
        file_put_contents($readmePath, $content);
    }
}