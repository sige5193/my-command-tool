<?php
namespace Action\Generate;
use Core\CommandActionAbstract;
use Core\OhaCore;
use Util\Java\JavaClassInfoMini;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
class AdminSiteFunctionalDocument extends CommandActionAbstract {
    /** @var string */
    private $siteSourcePath = null;
    
    /**
     * 产品业务中心功能文档生成
     * @param string $baseUrl 产品业务中心URL
     * @param string $cookie 当前登录用户的Cookie值
     * @param string $name 功能名称
     * @param string $outpath 文档输出路径
     */
    protected function run( $baseUrl, $cookie, $name, $outpath, $siteSourcePaht ) {
        $this->siteSourcePath = $siteSourcePaht;
        
        $funcItems = $this->getSubFunctionListFromMenu($name);
        
        $funcs = array();
        foreach ( $funcItems as $index => $item ) {
            $func = array();
            $func['url'] = $item['url'];
            $func['name'] = $item['name'];
            $func['labels'] = $this->parseUILabels($siteSourcePaht, $item);
            $func['buttons'] = $this->parseUIButtons($siteSourcePaht, $item, $func);
            $func['logic'] = $this->parseUILogic($siteSourcePaht, $item, $func);
            $func['screen'] = $this->getScreenShot($siteSourcePaht, $item, $func);
            $funcs[] = $func;
        }
        
        $html = $this->renderView('Data/Template/AdminSiteFunctionalDoc.php', array('funcs'=>$funcs));
        file_put_contents('doc.html', $html);
    }
    
    /** @return array */
    private function getScreenShot($siteSourcePaht, $item, $func) {
        $host = 'http://127.0.0.1:4444/wd/hub'; // this is the default
        $capabilities = DesiredCapabilities::chrome();
        $driver = RemoteWebDriver::create($host, $capabilities, 5000);
        echo "OK";
    }
    
    /** @return array */
    private function parseUILogic($siteSourcePaht, $item, $func) {
        $logics = array();
        foreach ( $func['buttons'] as $button ) {
            if ( !isset($button['class']) ) {
                continue;
            }
            
            $logic = array();
            /** @var $class \Util\Java\JavaClassInfoMini */
            $class = $button['class'];
            $callChain = $class->getMethodCallChain($button['method']);
            if ( empty($callChain) ) {
                $this->info("failed to get call chain of %s#%s", $class->getClassInfo('name'), $button['method']);
                continue;
            }
            $properties = $class->getClassInfo('property');
            $callService = null;
            foreach ( $callChain as $callerName => $callMethodName ) {
                if ( isset($properties[$callerName]) 
                && ('Service'==substr($properties[$callerName]['type'], -7)
                    || 'Servce'==substr($properties[$callerName]['type'], -6)
                    )) {
                    $callService = $properties[$callerName]['type'];
                    break;
                }
            }
            $implClassPath = $this->getTargetPathByMenuItemUrl($siteSourcePaht, $item['url'], 'serviceimpl', $callService);
            $implClass = JavaClassInfoMini::parse($implClassPath);
            $logic['name'] = $implClass->getMethodAttr($callMethodName, 'description');
            $logic['code'] = sprintf("%s#%s", $implClass->getClassInfo('name'), $callMethodName);
            
            # 解析输入参数, 假设所有参数都存在于parameter。
            $logic['input'] = array();
            $methodContent = $implClass->getMethodAttr($callMethodName, 'content');
            preg_match_all('`parameter\.get\("(?P<name>.*?)"\)`', $methodContent, $inPutMatch);
            $logic['input'] = $inPutMatch['name'];
            
            # 解析输出，直接获取方法返回值
            $logic['output'] = $implClass->getMethodAttr($callMethodName, 'return');
            
            # 解析处理过程
            $logic['process'] = '';
            
            $logics[] = $logic;
        }
        return $logics;
    }
    
    /** @return array */
    private function parseUIButtons($siteSourcePaht, $item, $func) {
        $doradoPath = $this->getTargetPathByMenuItemUrl($siteSourcePaht, $item['url'], 'dorado');
        
        $formContainer = null;
        $uistruct = simplexml_load_string(file_get_contents($doradoPath));
        if ( 'TabControl' == $uistruct->View->TabControl->getName() ) {
            $firseTab = $uistruct->View->TabControl->ControlTab[0];
            if ( "" !== $firseTab->Panel->getName() ) {
                $toolbarContainer = $firseTab->Panel->Children;
            } else {
                $toolbarContainer = $firseTab->Container;
            }
            $toolbarContainer = $toolbarContainer->FieldSet[1]->Children->ToolBar;
        } else {
            $toolbarContainer = $uistruct->View->FieldSet[1]->Children->ToolBar;
        }
        
        # 获取所有Action元素列表，并假设一个事件里面仅调用一个Action。
        $actions = array();
        foreach ( $uistruct->View->UpdateAction as $pageAction ) {
            $action = array();
            $action['name'] = $this->getXMLAttr($pageAction, 'id');
            $action['dataResolver'] = $this->getUIXMLProperty($pageAction, 'dataResolver');
            $actions[$action['name']] = $action;
        }
        foreach ( $uistruct->View->AjaxAction as $pageAction ) {
            $action = array();
            $action['name'] = $this->getXMLAttr($pageAction, 'id');
            $action['dataResolver'] = $this->getUIXMLProperty($pageAction, 'service');
            $actions[$action['name']] = $action;
        }
        $actionMatchPattern = '#NON-EXISTS#';
        if ( !empty($actions) ) {
            $actionMatchPattern = sprintf('#(?P<action>%s)#is', implode('|', array_keys($actions)));
        }
        
        $uiButtons = array();
        $buttonContainer = 'Button';
        if ( "" != $toolbarContainer->ToolBarButton->getName() ) {
            $buttonContainer = 'ToolBarButton';
        }
        foreach ( $toolbarContainer->{$buttonContainer} as $xmlButton ) {
            $button = array();
            $button['id'] = $this->getXMLAttr($xmlButton, 'id', uniqid("P".rand(0,9999)));
            $button['name'] = $this->getUIXMLProperty($xmlButton, 'caption');
            $button['classMethod'] = '';
            $button['description'] = '';
            
            $clientEvent = (string)$xmlButton->ClientEvent;
            if ( false !== strpos($clientEvent, 'execute') ) {
                preg_match($actionMatchPattern, $clientEvent, $actionMatch);
                if ( empty($actionMatch) ) {
                    $this->info("button `{$button['id']}` aka `{$button['name']}` in {$item['name']} has no action definded.");
                } else {
                    $button['classMethod'] = $actions[$actionMatch['action']]['dataResolver'];
                    list($class, $method) = explode('#', $button['classMethod']);
                    $classType = ('R'===$class[strlen($class)-1]) ? 'dataresolver' : 'dataprovider';
                    $classPath = $this->getTargetPathByMenuItemUrl($this->siteSourcePath, $func['url'], $classType, $class);
                    $javaClass = JavaClassInfoMini::parse($classPath);
                    $button['class'] = $javaClass;
                    $button['method'] = $method;
                    $button['description'] = $javaClass->getMethodAttr($method, 'description');
                }
            }
            $uiButtons[$button['id']] = $button;
        }
        return $uiButtons;
    }
    
    /** @return array */
    private function parseUILabels($siteSourcePaht, $item) {
        $doradoPath = $this->getTargetPathByMenuItemUrl($siteSourcePaht, $item['url'], 'dorado');
        
        $formContainer = null;
        $uistruct = simplexml_load_string(file_get_contents($doradoPath));
        if ( 'TabControl' == $uistruct->View->TabControl->getName() ) {
            $firseTab = $uistruct->View->TabControl->ControlTab[0];
            if ( "" !== $firseTab->Panel->getName() ) {
                $formContainer = $firseTab->Panel->Children;
            } else {
                $formContainer = $firseTab->Container;
            }
            $formContainer = $formContainer->FieldSet[0]->Children->AutoForm;
        } else {
            $formContainer = $uistruct->View->FieldSet[0]->Children->AutoForm;
        }
        
        $dataTypeName = $this->getUIXMLProperty($formContainer, 'dataType');
        if ( null === $dataTypeName ) {
            $dataSetName = $this->getUIXMLProperty($formContainer, 'dataSet');
            $dateSet = $this->getXMLChildrenByAttr($uistruct->View,'DataSet','id', $dataSetName);
            $dataTypeName = $this->getUIXMLProperty($dateSet, 'dataType');
            $dataTypeName = trim($dataTypeName, '[]');
        }
        
        $dataType = $this->getXMLChildrenByAttr($uistruct->Model,'DataType','name', $dataTypeName);
        $dataTypeLabels = array();
        foreach ( $dataType->PropertyDef as $property ) {
            $labelName = $this->getXMLAttr($property, 'name');
            $dataTypeLabels[$labelName]['name'] = $labelName;
            $dataTypeLabels[$labelName]['type'] = $this->getUIXMLProperty($property, 'dataType');
            $dataTypeLabels[$labelName]['text'] = $this->getUIXMLProperty($property, 'label');
            $dataTypeLabels[$labelName]['default'] = $this->getUIXMLProperty($property, 'defaultValue');
            $dataTypeLabels[$labelName]['required'] = $this->getUIXMLProperty($property, 'required');
        }
        
        $uiLabels = array();
        foreach ( $formContainer->AutoFormElement as $formElem ) {
            $name = $this->getUIXMLProperty($formElem, 'property');
            $uiLabels[$name] = $dataTypeLabels[$name];
        }
        return $uiLabels;
    }
    
    /** @return mixed */
    private function getXMLChildrenByAttr( \SimpleXMLElement $elem, $container, $attr, $value ) {
        $targetElem = null;
        foreach ( $elem->{$container} as $childElem ) {
            $tempElem = (array)$childElem;
            if ( $tempElem['@attributes'][$attr] == $value ) {
                $targetElem = $childElem;
                break;
            }
        }
        return $targetElem;
    }
    
    /** @return string  */
    private function getUIXMLProperty( \SimpleXMLElement $elem, $name ) {
        $value = null;
        foreach ( $elem->Property as $property ) {
            $property = (array)$property;
            if ( empty($property) ) {
                continue;
            }
            if ( $name == $property['@attributes']['name'] ) {
                $value = $property[0];
                break;
            }
        }
        return $value;
    }
    
    /** @return string */
    private function getXMLAttr( \SimpleXMLElement $elem, $name, $default=null ) {
        $tmpElem = (array)$elem;
        if ( !isset($tmpElem['@attributes']) ) {
            return $default;
        }
        if ( !isset($tmpElem['@attributes'][$name]) ) {
            return $default;
        }
        return $tmpElem['@attributes'][$name];
    }
    
    /** @return string */
    private function getTargetPathByMenuItemUrl( $siteSourcePaht, $url, $type, $name=null ) {
        $url = explode('.', $url);
        array_pop($url);
        $mainName = array_pop($url);
        array_pop($url);
        if ( null !== $name ) {
            $mainName = $name;
        }
        
        $path = "{$siteSourcePaht}/src/main/java";
        switch ( $type ) {
        case 'serviceimpl' :
            $path = sprintf('%s/%s/service/impl/%sImpl.java',$path, implode('/', $url), $mainName);
            break;
        case 'dataprovider' :
            $mainName = str_replace('DP', '', $mainName);
            $path = sprintf('%s/%s/dataprovider/%sDataProvider.java',$path, implode('/', $url), $mainName);
            break;
        case 'dataresolver' :
            $mainName = str_replace('DR', '', $mainName);
            $path = sprintf('%s/%s/dataresolver/%sDataResolver.java',$path, implode('/', $url), $mainName);
            break;
        case 'dorado' : 
            $path = sprintf('%s/%s/dorado/%s.view.xml',$path, implode('/', $url), $mainName);
            break;
        }
        
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        return $path;
    }
    
    /**
     * @param string $name
     * @return array
     */
    private function getSubFunctionListFromMenu( $name, $menu=null ) {
        if ( null === $menu ) {
            $config = OhaCore::system()->getConfig();
            $menu = $config['ShgtSiteAdmin']['MenuItem']['data'];
        }
        
        $docItem = null;
        foreach ( $menu as $item ) {
            if ( $name === trim($item['name']) ) {
                $docItem = $item;
                break;
            }
            if ( !empty($item['children']) ) {
                $funcList = $this->getSubFunctionListFromMenu($name, $item['children']);
                if ( null !== $funcList ) {
                    return $funcList;
                }
            }
        }
        
        if ( null === $docItem ) {
            return null;
        }
        
        return $this->getLastMenuItemOfNode($docItem);
    }
    
    /**
     * @param array $node
     * @return array
     */
    private function getLastMenuItemOfNode( $node ) {
        $items = array();
        foreach ( $node['children'] as $child ) {
            if (empty($child['children'])) {
                $items[] = $child;
            } else {
                $items = array_merge($items, $this->getLastMenuItemOfNode($child));
            }
        }
        return $items;
    }
}