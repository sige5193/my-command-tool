<?php
namespace Action\Shgt;
use Core\CommandActionAbstract;
use Core\OhaCore;
use Core\Util;
class MoveLocalServiceToRemoteService extends CommandActionAbstract {
    /** 匹配接口所有方法定义 */
    const REGEX_INTERFACE_METHODS = '`(public)*\s+?(?P<returnType>[\w<>,\s]+?)\s+?(?P<name>\w+?)\((?P<paramList>.*?)\).*?;`is';
    /** 匹配类所有方法定义 */
    const REGEX_CLASS_METHODS = '`(?P<methodDefination>public\s+?(?P<returnType>[\w<>\s,]*?)\s+?(?P<methodName>\w+?)\((?P<paramList>[\w<>,\s]*?)\)).*?\{(?P<methodContent>.*?)\n\t\}`isx';
    /** 匹配Mapper XML 的SQL定义头  */
    const REGEX_MAPPER_XML_SQL_HEAD = '#<(?P<action>select|delete|insert|update)(\s+?((id="(?P<sqlID>.*?)")|(parameterType=".*?")|(resultMap=".*?")|(resultType=".*?")))+\s*?>#is';
    /** 匹配导入的package */
    const REGEX_CLASS_IMPORT = '#import\s+?(?P<import>[\w.]+\.(?P<name>[\w.]+))\s*?;#is';
    /** 匹配Package */
    const REGEX_PACKAGE = '#package\s+?[\w.]+;#is';
    /** 匹配LoginUtil操作 */
    const REGES_LOGIN_UTIL = '#LoginUtil\s+?loginUtil\s*?=\s*?new\s+?LoginUtil\\(.*?\\);#is';
    /** 正则模板，匹配Mapper XML 定义的 SQL */
    const TMPL_REGEX_MAPPER_XML_SQL = '#<%s(\s+?((id="%s")|(parameterType=".*?")|(resultMap=".*?")))+\s*?>.*?</%s>#is';
    /** 本地服务基础路径 */
    private $localServiceBasePath = null;
    /** 接口基础路径  */
    private $interfaceServiceBasePath = null;
    /** 服务实现基础路径 */
    private $implementServiceBasePath = null;
    /** 服务基础名称 */
    private $baseServiceName = null;
    /** 模块名称 */
    private $moduleName = null;
    /** 是否需要停止迁移 */
    private $shouldStopMoving = false;
    /** Command 前缀 */
    private $commandPrefix = null;
    /** 下一步需要处理的数据 */
    private $nextStepData = array(
        # addCommonInfoCommandRequiredMethods => 需要增加CommonInfoCommand的方法列表
        # selfMappers => 需要移动的mapper列表
        # selfModels => 需要移动的model列表
        # selfMapperXMLs => 需要移动的Mapper XML列表
    );
    /** 任务信息 */
    private $taskInfo = array(
        'moduleName' => null,
        'serviceName' => null,
        'serviceImplName' => null,
        'implementServicePath' => null,
        'interfaceServicePath' => null,
        'commands' => array(),
        'mapperInterfaces' => array(),
        'mapperXMLs' => array(),
        'models' => array(),
    );
    
    /**
     * 将本地服务移动到远程服务中。
     * @param string $service 需要移动的服务名称
     * @param string $localPath 本地服务代码根目录地址
     * @param string $remotePath 远程服务代码根目录地址 
     * @param string $interfacePath 接口代码根目录
     */
    public function run ( $service, $localPath, $remotePath, $interfacePath, $commandPrefix ) {
        $this->commandPrefix = $commandPrefix;
        $this->setupBaseEnv($service, $localPath, $remotePath, $interfacePath);
        $this->printLine(0, '');
        
        # Step 1 : 移动服务定义到接口
        $this->shouldStopMoving or $this->moveServiceInterfaceToRemote();
        $this->printLine(0, '');
        
        # Step 2 : 移动实现到远程实现
        $this->shouldStopMoving or $this->moveServiceImplementToRemote();
        $this->printLine(0, '');
        
        # Step 3 : 修改服务定义参数增加CommonInfoCommand
        $this->shouldStopMoving or $this->addCommonInfoCommandToInterfaceMethod();
        $this->printLine(0, '');
        
        # Step 4 : 移动mapper接口到远程实现
        $this->shouldStopMoving or $this->moveMapperInterfaceToRemoteImplement();
        $this->printLine(0, '');
        
        # Step 5 : 移动Mapper XML文件
        $this->shouldStopMoving or $this->moveMapperXMLToRemoteImplement();
        $this->printLine(0, '');
        
        # Step 6 : 将需要的Model移动到接口中
        $this->shouldStopMoving or $this->moveModelToApiDefination();
        $this->printLine(0, '');
        
        # Step 7 : 替换参数为Command
        $this->shouldStopMoving or $this->convertParameterToCommand();
        $this->printLine(0, '');
        
        # Step 8 : 注册服务到HSF Provider
        $this->shouldStopMoving or $this->registerServiceToHsfProvide();
        $this->printLine(0, '');
        
        $this->printLine(0, '');
        $this->printLine(0, "模块名称: %s", $this->taskInfo['moduleName']);
        $this->printLine(0, "服务名称：%s", $this->taskInfo['serviceName']);
        $this->printLine(0, "服务接口文件： %s", $this->taskInfo['interfaceServicePath']);
        $this->printLine(0, "服务实现文件： %s", $this->taskInfo['implementServicePath']);
        $this->printLine(0, "Model 文件：");
        foreach ( $this->taskInfo['models'] as $model ) {
            $this->printLine(1, $model);
        }
        $this->printLine(0, "Mapper接口文件：");
        foreach ( $this->taskInfo['mapperInterfaces'] as $mapperInterface ) {
            $this->printLine(1, $mapperInterface);
        }
        $this->printLine(0, "Mapper XML 文件：");
        foreach ( $this->taskInfo['mapperXMLs'] as $mapperXML ) {
            $this->printLine(1, $mapperXML);
        }
        $this->printLine(0, "参数Command文件：");
        foreach ( $this->taskInfo['commands'] as $commandPath ) {
            $this->printLine(1, $commandPath);
        }
        $this->printLine(0, "迁移完成");
    }
    
    /** 注册服务到HSF Provider */
    private function registerServiceToHsfProvide() {
        $this->printLine(0, '注册服务到HSF Provider : 开始');
        $hsfConfig = array();
        $hsfConfig[] = "    <bean id=\"{$this->taskInfo['serviceImplName']}\" class=\"com.oyys.yjyg.service.yy.{$this->moduleName}.service.impl.{$this->taskInfo['serviceImplName']}\"/>";
        $hsfConfig[] = "    <hsf:provider id=\"{$this->taskInfo['serviceName']}\" interface=\"com.oyys.yjyg.service.yy.api.{$this->moduleName}.service.{$this->taskInfo['serviceName']}\" version=\"0.0.2\" ref=\"{$this->taskInfo['serviceImplName']}\" clientTimeout=\"50000\" group=\"yjyg_yy_\${activeprofile}\" />";
        $hsfConfig = implode("\n", $hsfConfig);
        
        $hsfConfigPath = str_replace(
            str_replace('/',DIRECTORY_SEPARATOR,"java/com/oyys/yjyg/service/yy/{$this->moduleName}/"),
            str_replace('/',DIRECTORY_SEPARATOR,'resources/META-INF/hsf-provider.xml'),
            $this->implementServiceBasePath);
        
        $hsfConfigContent = file_get_contents($hsfConfigPath);
        $hsfConfigContent = str_replace('<!-- 结算采购相关 -->', 
            "<!-- 结算采购相关 -->\n$hsfConfig", $hsfConfigContent);
        file_put_contents($hsfConfigPath, $hsfConfigContent);
        
        $this->printLine(0, '注册服务到HSF Provider : 完成');
    }
    
    /** 替换参数列表为Command */
    private function convertParameterToCommand() {
        $this->printLine(0, "Command化参数 ：开始");
        
        $interfacePath = $this->taskInfo['interfaceServicePath'];
        $implementPath = $this->taskInfo['implementServicePath'];
        $interfaceContent = file_get_contents($interfacePath);
        $implementContent = file_get_contents($implementPath);
        $commandBasePath = "{$this->interfaceServiceBasePath}command/";
        $this->printLine(1, "Command 路径：%s", $commandBasePath);
        
        $importList = array();
        preg_match_all(self::REGEX_CLASS_IMPORT, $interfaceContent, $matchedImports);
        foreach ( $matchedImports['name'] as $index => $importName ) {
            $importList[$importName] = $matchedImports['import'][$index];
        }
        
        $commands = array();
        preg_match_all(self::REGEX_INTERFACE_METHODS, $interfaceContent, $matchedInterfaceMethods);
        foreach ( $matchedInterfaceMethods['paramList'] as $index => $paramList ) {
            $params = $this->paramListStringToArray($paramList);
            
            $command = array();
            $command['name'] = ucfirst($this->commandPrefix.$matchedInterfaceMethods['name'][$index]);
            $command['rand'] = rand(1000, 2000).rand(1000, 9999).rand(1000, 9999).rand(1000, 9999).rand(100, 999);
            $command['package'] = "com.oyys.yjyg.service.yy.api.{$this->moduleName}.command";
            $command['valueName'] = lcfirst($command['name']);
            $command['attrs'] = array();
            
            $commandContent = array();
            $commandContent[] = "package com.oyys.yjyg.service.yy.api.{$this->moduleName}.command;";
            $commandContent[] = "import com.oyys.yjyg.service.yy.api.base.command.AbstractCommonInfoCommand;";
            foreach ( $params as $name => $dataType ) {
                $command['attrs'][$name] = $dataType;
                preg_match_all('#\w+#is', $dataType, $matchedDataTypes);
                foreach ( $matchedDataTypes[0] as $matchedDataType ) {
                    if ( isset($importList[$matchedDataType]) ) {
                        $commandContent[] = "import {$importList[$matchedDataType]};";
                    }
                }
            }
            $commandContent[] = "import lombok.Getter;";
            $commandContent[] = "import lombok.Setter;";
            $commandContent[] = "@Getter";
            $commandContent[] = "@Setter";
            $commandContent[] = "public class {$command['name']}Command extends AbstractCommonInfoCommand{";
            $commandContent[] = "    private static final long serialVersionUID = {$command['rand']}L;";
            foreach ( $params as $name => $dataType ) {
               $commandContent[] = "    private {$dataType} {$name};";
            }
            $commandContent[] = "}";
            $commandContent = implode("\n", $commandContent);
            
            $commandPath = "{$commandBasePath}{$command['name']}Command.java";
            file_put_contents($commandPath, $commandContent);
            $this->taskInfo['commands'][] = $commandPath;
            $this->printLine(1, "生成 Command ：%sCommand", $command['name']);
            
            $commands[$matchedInterfaceMethods['name'][$index]] = $command;
        }
        
        $this->printLine(1, "替换接口参数为Command");
        preg_match_all(self::REGEX_INTERFACE_METHODS, $interfaceContent, $matchedInterfaceMethods);
        foreach ( $matchedInterfaceMethods['name'] as $index => $methodName ) {
            $command = $commands[$methodName];
            $newParam = "{$command['name']}Command {$command['valueName']}Command";
            $defineation = str_replace($matchedInterfaceMethods['paramList'][$index], $newParam, $matchedInterfaceMethods[0][$index]);
            $interfaceContent = str_replace($matchedInterfaceMethods[0][$index], $defineation, $interfaceContent);
            
            preg_match(self::REGEX_PACKAGE, $interfaceContent, $matchedPackage);
            $interfaceContent = str_replace(
                $matchedPackage[0], 
                "{$matchedPackage[0]}\nimport {$command['package']}.{$command['name']}Command;", 
                $interfaceContent);
        }
        file_put_contents($interfacePath, $interfaceContent);
        
        $this->printLine(1, "替换方法参数为Command，并且参数改为变量定义。");
        preg_match_all(self::REGEX_CLASS_METHODS, $implementContent, $matchedImplementMethods);
        foreach ( $matchedImplementMethods['methodName'] as $index => $methodName ) {
            $methodParams = $this->paramListStringToArray($matchedImplementMethods['paramList'][$index]);
            $methodParamNames = array_keys($methodParams);
            
            $command = $commands[$methodName];
            $newParam = "{$command['name']}Command {$command['valueName']}Command";
            $defineation = str_replace($matchedImplementMethods['paramList'][$index], $newParam, $matchedImplementMethods['methodDefination'][$index]);
            $implementContent = str_replace($matchedImplementMethods['methodDefination'][$index], $defineation, $implementContent);
            
            # 在方法头部放入参数的定义
            $methodContent = $matchedImplementMethods['methodContent'][$index];
            $paramDefinations = array();
            $paramIndex = 0;
            foreach ( $command['attrs'] as $attrName => $attrType ) {
                $methodParamName = $methodParamNames[$paramIndex];
                $paramDefinations[] = "        {$attrType} {$methodParamName} = {$command['valueName']}Command.get".ucfirst($attrName)."();";
                $paramIndex ++;
            }
            $implementContent = str_replace(
                $matchedImplementMethods['methodContent'][$index], 
                "\n".implode("\n", $paramDefinations)."\n".$methodContent, 
                $implementContent);
            
            preg_match(self::REGEX_PACKAGE, $implementContent, $matchedPackage);
            $implementContent = str_replace(
                $matchedPackage[0],
                "{$matchedPackage[0]}\nimport {$command['package']}.{$command['name']}Command;",
                $implementContent);
        }
        file_put_contents($implementPath, $implementContent);
        
        $this->printLine(0, "Command化参数 ：结束");
    }
    
    /** 移动Mapper的XML文件 */
    private function moveMapperXMLToRemoteImplement() {
        $this->printLine(0, "移动Mapper的XML文件 ： 开始");
        $localMapperBasePath = str_replace(
            str_replace('/',DIRECTORY_SEPARATOR,"java/com/baosight/shgt/site/yy/{$this->moduleName}/"),
            str_replace('/',DIRECTORY_SEPARATOR,"resources/sql/{$this->moduleName}/"),
            $this->localServiceBasePath);
        $remoteMapperBasePath = str_replace(
            str_replace('/',DIRECTORY_SEPARATOR,"java/com/oyys/yjyg/service/yy/{$this->moduleName}/"),
            str_replace('/',DIRECTORY_SEPARATOR,'resources/mapping/'),
            $this->implementServiceBasePath);
        
        foreach ( $this->nextStepData['selfMapperXMLs'] as $name => $package ) {
            $this->printLine(1, "处理Mapper XML：%s", $name);
            $localMapperPath = str_replace('/', DIRECTORY_SEPARATOR, "{$localMapperBasePath}/{$name}.xml");
            $remoteMapperPath = str_replace('/', DIRECTORY_SEPARATOR, "{$remoteMapperBasePath}/{$name}.xml");
            $this->taskInfo['mapperXMLs'][] = "{$name}.xml";
            
            if ( !is_file($remoteMapperPath) ) {
                $this->printLine(2, "目标XML不存在，直接复制本地XML");
                copy($localMapperPath, $remoteMapperPath);
                $remoteContent = file_get_contents($remoteMapperPath);
                
                # 修改路径
                $remoteContent = preg_replace(
                    "#<mapper\s+?namespace=\"com\\.baosight\\.shgt\\.site\\.yy\\..*?\\.mapper\\.{$name}\"\s*?>#is", 
                    "<mapper namespace=\"com.oyys.yjyg.service.yy.{$this->moduleName}.mapper.{$name}\" >", 
                    $remoteContent);
                $this->printLine(2, "修改XML namespace");
                file_put_contents($remoteMapperPath, $remoteContent);
            } else {
                $this->printLine(2, "目标XML已存在，进行合并");
                # 比较 resultMap
                $localContent = file_get_contents($localMapperPath);
                $remoteContent = file_get_contents($remoteMapperPath);
                
                preg_match('#<resultMap\s+?id="BaseResultMap"\s+?type="(?P<type>.*?)"\s+?>(?P<content>.*?)</resultMap>#is', $localContent, $matchedLocalResultMap);
                preg_match('#<resultMap\s+?id="BaseResultMap"\s+?type="(?P<type>.*?)"\s+?>(?P<content>.*?)</resultMap>#is', $remoteContent, $matchedRemoteResultMap);
                
                $isBaseResultMap2Added = false;
                if ( empty($matchedRemoteResultMap) ) {
                    preg_match('#<mapper\s+?namespace=".*?"\s+?>#is', $remoteContent, $matchedMapperHead);
                    $matchedRemoteResultMap[0] = $matchedMapperHead[0];
                    $matchedRemoteResultMap['content'] = '';
                }
                if ( empty($matchedLocalResultMap) ) {
                    $matchedLocalResultMap['content'] = '';
                }
                preg_match_all('#<(id|result)\s+?column="(?P<column>.*?)".*?>#is', $matchedLocalResultMap['content'], $matchedLocalColumn);
                preg_match_all('#<(id|result)\s+?column="(?P<column>.*?)".*?>#is', $matchedRemoteResultMap['content'], $matchedRemoteColumn);
                if ( implode(',', $matchedLocalColumn['column']) !== implode(',', $matchedRemoteColumn['column']) ) {
                    $this->printLine(2, "增加BaseResultMap2");
                    # 增加 BaseResultMap2
                    $matchedLocalResultMap[0] = str_replace('BaseResultMap', 'BaseResultMap2', $matchedLocalResultMap[0]);
                    $remoteContent = str_replace($matchedRemoteResultMap[0], "{$matchedRemoteResultMap[0]}\n  {$matchedLocalResultMap[0]}", $remoteContent);
                    $isBaseResultMap2Added = true;
                }
                
                # 比较Mapper 实现方法
                $this->printLine(2, '比较Mapper 实现方法');
                preg_match_all(self::REGEX_MAPPER_XML_SQL_HEAD, $localContent, $matchedLocalSQLs);
                preg_match_all(self::REGEX_MAPPER_XML_SQL_HEAD, $remoteContent, $matchedRemoteSQLs);
                $sqlDiff = array_diff($matchedLocalSQLs['sqlID'], $matchedRemoteSQLs['sqlID']);
                if ( !empty($sqlDiff) ) {
                   foreach ( $sqlDiff as $sqlId ) {
                       $index = array_search($sqlId, $matchedLocalSQLs['sqlID']);
                       $pattern = sprintf(self::TMPL_REGEX_MAPPER_XML_SQL,
                           $matchedLocalSQLs['action'][$index], 
                           $matchedLocalSQLs['sqlID'][$index], 
                           $matchedLocalSQLs['action'][$index]);
                       preg_match($pattern, $localContent, $matchedLocalSQL);
                       if ( $isBaseResultMap2Added ) {
                           $matchedLocalSQL[0] = str_replace('BaseResultMap', 'BaseResultMap2', $matchedLocalSQL[0]);
                       }
                       $remoteContent = str_replace("</mapper>", "  {$matchedLocalSQL[0]}\n</mapper>", $remoteContent);
                       $this->printLine(2, "增加SQL定义 : %s", $matchedLocalSQLs['sqlID'][$index]);
                   }
                }
                
                file_put_contents($remoteMapperPath, $remoteContent);
            }
            
            $remoteContent = file_get_contents($remoteMapperPath);
            
            # 修改对应Model的路径
            $this->printLine(1, '修改对应Model的路径');
            $modelName = str_replace('Mapper', '', $name);
            $remoteContent = str_replace(
                "com.baosight.shgt.site.yy.{$this->moduleName}.model.{$modelName}", 
                "com.oyys.yjyg.service.yy.api.{$this->moduleName}.model.{$modelName}", 
                $remoteContent);
            
            file_put_contents($remoteMapperPath, $remoteContent);
        }
        
        $this->printLine(0, "移动Mapper的XML文件 ： 完成");
    }
    
    /** 移动mapper接口到远程实现  */
    private function moveMapperInterfaceToRemoteImplement() {
        $this->printLine(0, "移动mapper接口到远程实现：开始");
        
        foreach ( $this->nextStepData['selfMappers'] as $name => $package ) {
            $this->taskInfo['mapperInterfaces'][] = $name;
            $this->printLine(1, "处理Mapper ： %s", $name);
            
            # 移动Mapper接口定义
            $targetMapperPath = "{$this->implementServiceBasePath}mapper/{$name}.java";
            $localMapperPath = "{$this->localServiceBasePath}mapper/{$name}.java";
            if ( !file_exists($targetMapperPath) ) {
                copy($localMapperPath, $targetMapperPath);
                $targetContent = file_get_contents($targetMapperPath);
                $targetContent = str_replace(
                    'package com.baosight.shgt.site.yy.settle.mapper;',
                    'package com.oyys.yjyg.service.yy.settle.mapper;',
                    $targetContent);
                file_put_contents($targetMapperPath, $targetContent);
                $this->printLine(2, "复制 Mapper接口文件 => %s", $targetMapperPath);
            } else {
                $this->printLine(2, "合并Mapper接口文件");
                # 合并Mapper
                $localMapperContent = file_get_contents($localMapperPath);
                $targetMapperContent = file_get_contents($targetMapperPath);
                
                $localMethods = array();
                preg_match_all(self::REGEX_INTERFACE_METHODS, $localMapperContent, $localMatchedMethods);
                foreach ( $localMatchedMethods[0] as $index => $methodDefinations ) {
                    $localMethods[$index] = preg_replace('#\s#is', '', $methodDefinations);
                }
                
                $targetMethos = array();
                preg_match_all(self::REGEX_INTERFACE_METHODS, $targetMapperContent, $targetMatchedMethods);
                foreach ( $targetMatchedMethods[0] as $index => $methodDefinations ) {
                    $targetMethos[$index] = preg_replace('#\s#is', '', $methodDefinations);
                }
                
                # 如果有多余的方法则移动到目标Mapper
                $moveMethodList = array_diff($localMethods, $targetMethos);
                foreach ( $moveMethodList as $moveMethodName ) {
                    $this->printLine(2, "合并接口： %s", $moveMethodName);
                    $index = array_search($moveMethodName, $localMethods);
                    $targetMapperContent = str_replace("\n}", "{$localMatchedMethods[0][$index]}\n}", $targetMapperContent);
                }
                file_put_contents($targetMapperPath, $targetMapperContent);
            }
            
            $targetContent = file_get_contents($targetMapperPath);
            # 提取所有model import地址，加入到下一步的操作列表中
            $this->printLine(1, '提取所有model import地址，加入到下一步的操作列表中');
            preg_match_all("#import\s+?com\\.baosight\\.shgt\\.site\\.yy\\.{$this->moduleName}\\.model\\.(?P<name>.*?);#is", 
                $targetContent, $matchedModels);
            foreach ($matchedModels['name'] as $modelName ) {
                $this->nextStepData['selfModels'][] = $modelName;
            }
            
            # 移动Model import地址
            $this->printLine(1, '移动Model import地址');
            $targetContent = preg_replace(
                "#import\s+?com\\.baosight\\.shgt\\.site\\.yy\\.{$this->moduleName}\\.model\\.(.*?);#is", 
                "import com.oyys.yjyg.service.yy.api.{$this->moduleName}.model.$1;",
                $targetContent);
            file_put_contents($targetMapperPath, $targetContent);
        }
        $this->nextStepData['selfMapperXMLs'] = $this->nextStepData['selfMappers'];
        $this->nextStepData['selfMappers'] = array();
        
        $this->printLine(0, "移动mapper接口到远程实现：结束");
    }
    
    /** 将需要的Model移动到接口中 */
    private function moveModelToApiDefination() {
        $this->printLine(0, '将需要的Model移动到接口中 ： 开始');
        foreach ( $this->nextStepData['selfModels'] as $modelName ) {
            $targetModelPath = "{$this->interfaceServiceBasePath}model/{$modelName}.java";
            if ( file_exists($targetModelPath) ) {
                $this->printLine(1, "{$modelName}.java 已存在");
                continue;
            }
            
            $this->taskInfo['models'][] = "{$modelName}.java";
            $sourceModelPath = "{$this->localServiceBasePath}/model/{$modelName}.java";
            copy($sourceModelPath, $targetModelPath);
            
            $targetContent = file_get_contents($targetModelPath);
            $targetContent = str_replace(
                'package com.baosight.shgt.site.yy.settle.model;', 
                'package com.oyys.yjyg.service.yy.api.settle.model;', 
                $targetContent);
            file_put_contents($targetModelPath, $targetContent);
            $this->printLine(1, "{$modelName}.java 移动完成");
        }
        $this->nextStepData['selfModels'] = array();
        
        $this->printLine(0, '将需要的Model移动到接口中 ： 结束');
    }
    
    /** 修改服务定义参数增加CommonInfoCommand */
    private function addCommonInfoCommandToInterfaceMethod() {
        $this->printLine(0, "修改服务定义参数增加CommonInfoCommand : 开始");
        $remoteInterfacePath = $this->interfaceServiceBasePath."service/{$this->baseServiceName}.java";
        $interfaceContent = file_get_contents($remoteInterfacePath);
        
        if ( empty($this->nextStepData['addCommonInfoCommandRequiredMethods']) ) {
            $this->printLine(1, "接口无CommonInfoCommand需要增加");
            return;
        }
        
        preg_match_all(self::REGEX_INTERFACE_METHODS, $interfaceContent, $matchedMethods);
        foreach ( $matchedMethods['name'] as $index => $methodName ) {
            if ( !in_array(trim($methodName), $this->nextStepData['addCommonInfoCommandRequiredMethods']) ) {
                continue;
            }
            
            $this->printLine(1, "修改方法： %s", trim($methodName));
            $methodDefination = $matchedMethods[0][$index];
            $newParamList = $this->addParamToParamListString($matchedMethods['paramList'][$index], 'CommonInfoCommand', 'commonInfoCommand');
            $methodDefination = str_replace($matchedMethods['paramList'][$index], $newParamList, $methodDefination);
            $interfaceContent = str_replace($matchedMethods[0][$index], $methodDefination, $interfaceContent);
        }
        
        preg_match('#import .*?;#is', $interfaceContent, $firstImport);
        $interfaceContent = str_replace(
            $firstImport[0], 
            "{$firstImport[0]}\nimport com.oyys.yjyg.service.yy.api.base.command.CommonInfoCommand;", 
            $interfaceContent);
        
        file_put_contents($remoteInterfacePath, $interfaceContent);
        
        $this->nextStepData['addCommonInfoCommandRequiredMethods'] = array();
        
        $this->printLine(0, "修改服务定义参数增加CommonInfoCommand : 完成");
    }
    
    /** 移动实现到远程实现 */
    private function moveServiceImplementToRemote() {
        $this->printLine(0, "移动实现到远程实现：开始");
        $this->taskInfo['serviceImplName'] = "{$this->baseServiceName}Impl";
        
        # 拷贝文件
        $localImplementPath = $this->localServiceBasePath."service/impl/{$this->baseServiceName}Impl.java";
        $remoteImplementPath = $this->implementServiceBasePath."service/impl/{$this->baseServiceName}Impl.java";
        copy($localImplementPath, $remoteImplementPath);
        $this->taskInfo['implementServicePath'] = $remoteImplementPath;
        $this->printLine(1, "拷贝文件：%s => %s", $localImplementPath, $remoteImplementPath);
        
        # 替换package名称
        $remoteImplContent = file_get_contents($remoteImplementPath);
        $remoteImplContent = preg_replace('#package\s+?com\\.baosight\\.shgt\\.site\\.yy\\.(.*?);#is',
            'package com.oyys.yjyg.service.yy.${1};', $remoteImplContent);
        $this->printLine(1,"替换package名称");
        
        # 替换实现的服务的import路径。
        $remoteImplContent = str_replace(
            "import com.baosight.shgt.site.yy.{$this->moduleName}.service.{$this->baseServiceName};", 
            "import com.oyys.yjyg.service.yy.api.{$this->moduleName}.service.{$this->baseServiceName};", 
            $remoteImplContent);
        $this->printLine(1, "替换实现的服务的import路径");
        
        # 将注解@Service 替换为 @ServiceProvider
        $remoteImplContent = preg_replace('#@Service\("(.*?)"\)#is','@ServiceProvider("${1}")',$remoteImplContent);
        $remoteImplContent = str_replace(
            'import org.springframework.stereotype.Service;', 
            'import com.baosight.shgt.api.base.configuration.ServiceProvider;', 
            $remoteImplContent);
        $this->printLine(1, "将注解@Service 替换为 @ServiceProvider");
        
        # 解析出所有使用的Mapper，返回给下一步， 用于移动Mapper。
        preg_match_all("#import\s+?(?P<package>com\\.baosight\\.shgt\\.site\\.yy\\.{$this->moduleName}\\.mapper\\.(?P<mapper>.*?));#is", 
            $remoteImplContent, $matchedMappers);
        $this->nextStepData['selfMappers'] = array();
        foreach ( $matchedMappers['mapper'] as $index => $mapperName ) {
            $this->nextStepData['selfMappers'][$mapperName] = $matchedMappers['package'][$index];
        }
        $this->printLine(1, "解析出所有使用的Mapper，返回给下一步， 用于移动Mapper");
        
        # 替换Mapper的package路径
        $remoteImplContent = preg_replace(
            "#import\s+?com\\.baosight\\.shgt\\.site\\.yy\\.{$this->moduleName}\\.mapper\\.(.*?);#is", 
            sprintf('import com.oyys.yjyg.service.yy.%s.mapper.$1;', $this->moduleName), 
            $remoteImplContent);
        $this->printLine(1, '替换Mapper的package路径');
        
        # 解析出所有使用LoginUtil的代码，对参数进行增加，以及替换LoginUtil的操作。
        preg_match_all(self::REGEX_CLASS_METHODS, $remoteImplContent, $matchedMethods);
        $this->printLine(1, '解析出所有使用LoginUtil的代码，对参数进行增加，以及替换LoginUtil的操作');
        
        $hasLoginUtil = false;
        $this->nextStepData['addCommonInfoCommandRequiredMethods'] = array();
        foreach ( $matchedMethods['methodContent'] as $index => $methodContent ){
            if ( 0 == preg_match(self::REGES_LOGIN_UTIL, $methodContent) ) {
                continue;
            }
            
            $hasLoginUtil = true;
            
            # 为参数列表增加CommonInfoCommand
            $methodDefination = $matchedMethods['methodDefination'][$index];
            $newParamList = $this->addParamToParamListString( $matchedMethods['paramList'][$index], 'CommonInfoCommand', 'commonInfoCommand');
            $methodDefination = str_replace($matchedMethods['paramList'][$index], $newParamList, $methodDefination);
            $remoteImplContent = str_replace($matchedMethods['methodDefination'][$index], $methodDefination, $remoteImplContent);
            
            # 加入到下一步增加接口参数列表。
            $this->nextStepData['addCommonInfoCommandRequiredMethods'][] = $matchedMethods['methodName'][$index];
        }
        
        # 清理掉所有LoginUtil的操作，替换成commonInfoCommand
        if ( $hasLoginUtil ) {
            $remoteImplContent = preg_replace(self::REGES_LOGIN_UTIL, '', $remoteImplContent);
            $remoteImplContent = str_replace('loginUtil.getLoginModel()', 'commonInfoCommand', $remoteImplContent);
            $remoteImplContent = str_replace(
                'import com.baosight.shgt.site.yy.util.LoginUtil;', 
                'import com.oyys.yjyg.service.yy.api.base.command.CommonInfoCommand;', 
                $remoteImplContent);
            $this->printLine(1, '清理掉所有LoginUtil的操作，替换成commonInfoCommand');
        }
        
        # 替换掉其他不存在的import package.
        $replaceMap = OhaCore::system()->getConfig();
        $replaceMap = $replaceMap['Shgt']['OldPackageReplacementMap'];
        foreach ( $replaceMap as $oldPackage => $newPackage ) {
            $remoteImplContent = str_replace("import {$oldPackage}", "import {$newPackage}", $remoteImplContent);
        }
        $this->printLine(1, '替换掉其他不存在的import package');
        
        # 匹配出需要移动的model列表
        preg_match_all("#import\s+?com\\.baosight\\.shgt\\.site\\.yy\\.{$this->moduleName}\\.model\\.(?P<name>.*?);#",
            $remoteImplContent, $matchedModels);
        foreach ( $matchedModels['name'] as $index => $modelName ) {
            $modelName = trim($modelName);
            $this->nextStepData['selfModels'][] = $modelName;
        
            $remoteImplContent = str_replace(
                "import com.baosight.shgt.site.yy.{$this->moduleName}.model.{$modelName};",
                "import com.oyys.yjyg.service.yy.api.{$this->moduleName}.model.{$modelName};",
                $remoteImplContent);
        }
        $this->printLine(1, '匹配出需要移动的model列表');
        
        # 替换 GenerationSequenceService
        $remoteImplContent = preg_replace(
            '#private\s+?GenerationSequenceService\s+?generationSequenceService;#is', 
            'private GenerationSequenceServiceImpl generationSequenceServiceImpl;', 
            $remoteImplContent);
        $remoteImplContent = preg_replace(
            '#generationSequenceService\\.obtainSequenceId\\("(.*?)"\\)\\.getSequenceID\\(\\)#is', 
            'generationSequenceServiceImpl.getSequenceId("$1")', $remoteImplContent);
        $this->printLine(1, '替换 GenerationSequenceService');
        
        file_put_contents($remoteImplementPath, $remoteImplContent);
        $this->printLine(0, "移动实现到远程实现：完成");
    }
    
    /** 移动服务定义到接口 */
    private function moveServiceInterfaceToRemote() {
        $this->printLine(0, "移动服务接口到API ： 开始");
        
        # 拷贝文件
        $localInterfacePath = $this->localServiceBasePath."service/{$this->baseServiceName}.java";
        $remoteInterfacePath = $this->interfaceServiceBasePath."service/{$this->baseServiceName}.java";
        if ( file_exists($remoteInterfacePath) ) {
            $this->shouldStopMoving = true;
            $this->printLine(1, "目标文件已存在：%s", $remoteInterfacePath);
            return;
        }
        
        $this->printLine(1, "拷贝文件 ：%s => %s", $localInterfacePath, $remoteInterfacePath);
        copy($localInterfacePath, $remoteInterfacePath);
        $this->taskInfo['interfaceServicePath'] = $remoteInterfacePath;
        
        # 替换package名称
        $remoteInterfaceConent = file_get_contents($remoteInterfacePath);
        $remoteInterfaceConent = preg_replace('#package\s+?com\\.baosight\\.shgt\\.site\\.yy\\.(.*?);#is', 
            'package com.oyys.yjyg.service.yy.api.${1};', $remoteInterfaceConent);
        $this->printLine(1, '替换package名称');
        
        # 打上迁移标记
        $remoteInterfaceConent = str_replace('public interface', 
            "/** Generate by my-command-tool written by michaelluthor. */\npublic interface", $remoteInterfaceConent);
        $this->printLine(1, '标记迁移文件');
        
        # 匹配出需要移动的model列表
        $this->printLine(1, "待移动的model列表：");
        preg_match_all("#import\s+?com\\.baosight\\.shgt\\.site\\.yy\\.{$this->moduleName}\\.model\\.(?P<name>.*?);#", 
            $remoteInterfaceConent, $matchedModels);
        foreach ( $matchedModels['name'] as $index => $modelName ) {
            $modelName = trim($modelName);
            $this->nextStepData['selfModels'][] = $modelName;
            $this->printLine(2, $modelName);
            
            $remoteInterfaceConent = str_replace(
                "import com.baosight.shgt.site.yy.{$this->moduleName}.model.{$modelName};", 
                "import com.oyys.yjyg.service.yy.api.{$this->moduleName}.model.{$modelName};", 
                $remoteInterfaceConent);
        }
        $this->printLine(1, "替换完成所有model的import路径");
        
        file_put_contents($remoteInterfacePath, $remoteInterfaceConent);
        
        $this->printLine(1, "目标服务接口路径 ：%s", $remoteInterfacePath);
        $this->printLine(0, "移动服务接口到API ： 完成");
    }
    
    /** 初始化工作环境 */
    private function setupBaseEnv($service, $localPath, $remotePath, $interfacePath ) {
        $this->printLine(0, "初始化环境 ：开始");
        
        $servicePart = $locaBasePath = explode('.', $service);
        array_pop($locaBasePath);
        array_pop($locaBasePath);
        array_unshift($locaBasePath, 'src','main','java');
        $this->localServiceBasePath = rtrim($localPath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .implode(DIRECTORY_SEPARATOR, $locaBasePath)
            .DIRECTORY_SEPARATOR;
        
        $this->moduleName = $servicePart[count($servicePart)-3];
        $this->interfaceServiceBasePath = rtrim($interfacePath, DIRECTORY_SEPARATOR)
            .str_replace('/', DIRECTORY_SEPARATOR, "/src/main/java/com/oyys/yjyg/service/yy/api/")
            .$this->moduleName.DIRECTORY_SEPARATOR;
        
        $this->implementServiceBasePath = rtrim($remotePath, DIRECTORY_SEPARATOR)
            .str_replace('/', DIRECTORY_SEPARATOR, "/src/main/java/com/oyys/yjyg/service/yy/")
            .$servicePart[count($servicePart)-3].DIRECTORY_SEPARATOR;
        
        $this->baseServiceName = $servicePart[count($servicePart)-1];
        
        $this->taskInfo['moduleName'] = $this->moduleName;
        $this->taskInfo['serviceName'] = $this->baseServiceName;
        
        $this->printLine(1, "模块名称 ：%s", $this->moduleName);
        $this->printLine(1, "服务名称 ：%s", $this->baseServiceName);
        $this->printLine(0, "初始化环境 ：完成");
    }
    
    /**
     * 为参数列表添加一个新的参数, 并返回新的参数列表
     * @param unknown $params
     * @param unknown $newParam
     */
    private function addParamToParamListString( $params, $type, $name ) {
        $params = trim($params).',';
        preg_match_all('`(?P<type>[\w<,>\s]+?)\s(?P<name>\w+)[,]`', $params, $matchedParams);
        
        $newParams = array();
        foreach ( $matchedParams['name'] as $index => $paranName ) {
            $paranName = trim($paranName);
            $paramType = trim($matchedParams['type'][$index]);
            $newParams[$paranName] = "{$paramType} {$paranName}";
        }
        $newParams[$name] = "{$type} {$name}";
        $newParams = implode(', ', $newParams);
        return $newParams;
    }
    
    /**
     * 转换参数字符串到数组
     * @param string $params
     * @return array
     */
    private function paramListStringToArray( $params ) {
        $params = trim($params).',';
        preg_match_all('`(?P<type>[\w<,>\s]+?)\s(?P<name>\w+)[,]`', $params, $matchedParams);
        
        $newParams = array();
        foreach ( $matchedParams['name'] as $index => $paranName ) {
            $paranName = trim($paranName);
            $paramType = trim($matchedParams['type'][$index]);
            $newParams[$paranName] = $paramType;
        }
        return $newParams;
    }
    
    /**
     * 显示一行字符串到控制台
     * @param unknown $ident
     * @param unknown $message
     */
    private function printLine($ident, $message) {
        $args = func_get_args();
        array_shift($args);
        $message = call_user_func_array('sprintf', $args);
        $ident = array_fill(0, $ident, '    ');
        Util::printf("%s%s\n", implode('', $ident), $message);
    }
}