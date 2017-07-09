<?php
namespace Core;
use Action\Help;
/**
 * @author michaelluthor
 */
class CommandActionAbstract {
    /** @var string */
    private $name = null;
    
    /** @return void */
    public function __construct( $name ) {
        $this->name = $name;
    }
    
    /**
     * @param array $parameters
     * @return integer
     */
    public function execute( $parameters ) {
        $actionParams = $this->parseCommandParams($parameters);
        if ( isset($actionParams['setup']) ) {
            if ( !file_exists($actionParams['setup']) ) {
                $this->error('ini file `%s` does not exists.', $actionParams['setup']);
            }
            $iniParams = parse_ini_file($actionParams['setup']);
            if ( false === $iniParams ) {
                $this->error('ini file `%s` parse failed.', $actionParams['setup']);
            }
            $iniParams = $this->parseArrayParams($iniParams);
            $actionParams = array_merge($iniParams,$actionParams);
        }
        
        if ( !is_callable(array($this, 'run')) ) {
            $this->error('action is not available.');
        }
        
        $methodInfo = new \ReflectionMethod($this, 'run');
        $methodParams = array();
        foreach ( $methodInfo->getParameters() as $index => $parameter ) {
            /** @var $parameter \ReflectionParameter */
            $name = $parameter->getName();
            if ( array_key_exists($name, $actionParams) ) {
                $methodParams[$name] = $actionParams[$name];
            } else if ( $parameter->isDefaultValueAvailable() ) {
                $methodParams[$name] = $parameter->getDefaultValue();
            } else {
                $help = new Help('help');
                $help->execute(array(sprintf('--name=%s',$this->name)));
                
                $this->error('parameter to command is not match.');
            }
        }
        
        try {
            call_user_func_array(array($this, 'run'), $methodParams);
        } catch ( \Exception $e ) {
            $this->error("[".get_class($e)."]". $e->getMessage());
        }
    }
    
    /**
     * @param string $message
     * @return void
     */
    protected function error( $message ) {
        $message = call_user_func_array('sprintf', func_get_args());
        printf("Error : %s\n", $message);
        exit();
    }
    
    /**
     * @param string $message
     * @return void
     */
    protected function info( $message ) {
        $message = call_user_func_array('sprintf', func_get_args());
        Util::printf("Info : %s\n", $message);
    }
    
    /**
     * @param array $params
     * @return array
     */
    private function parseCommandParams( $params ) {
        $actionParams = array();
        foreach ( $params as $index => $param ) {
            if ( '--' !== substr($param, 0, 2) ) {
                continue;
            }
            if ( false == strpos($param, '=') ) {
                $name = trim(substr($param, 2));
                $value = true;
            } else {
                $name = trim(substr($param, 2, strpos($param, '=')-2));
                $value = trim(substr($param, strpos($param, '=')+1));
            }
            $actionParams[$name] = $value;
        }
        return $this->parseArrayParams($actionParams);
    }
    
    /**
     * @param array $params
     * @return array
     */
    private function parseArrayParams( $params ) {
        $actionParams = array();
        foreach ( $params as $name => $value ) {
            if ( in_array($value, array('on','true','1','yes')) ) {
                $value = true;
            } else if ( in_array($value, array('off','false','0','no'))) {
                $value = false;
            }
        
            $name = Util::strMiddleSnakeToLcfirstCamel($name);
            $actionParams[$name] = $value;
        }
        return $actionParams;
    }
    
    /** @return string  */
    protected function renderView( $view, $data=array() ) {
        extract($data);
        
        ob_start();
        ob_implicit_flush(false);
        require OhaCore::system()->getPath($view);
        $content = ob_get_clean();
        return $content;
    }
}