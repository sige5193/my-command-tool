<?php
namespace Util\Java;
class JavaClassInfoMini {
    /** @var array */
    private static $cachedClass = array();
    /** @var string */
    private $path = null;
    /** @var string */
    private $fileContent = null;
    /** @var array */
    private $classInfo = array();
    
    /** @return self */
    public static function parse($path) {
        if ( !isset(self::$cachedClass[$path]) ) {
            self::$cachedClass[$path] = new JavaClassInfoMini($path);
        }
        return self::$cachedClass[$path];
    }
    
    /** @param string $path */
    private function __construct( $path ) {
        $this->path = $path;
        $this->fileContent = file_get_contents($path);
        $this->parseFile();
    }
    
    /** @return void */
    private function parseFile() {
        # match class name
        if ( preg_match('#public\s*class\s*(?P<class>.*?)\s#is', $this->fileContent, $match) ) {
            $this->classInfo['name'] = $match['class'];
        }
        
        $classContent = substr($this->fileContent, strpos($this->fileContent, $match[0]));
        $classContent = substr($classContent, strlen($match[0]));
        
        # match proto
        preg_match_all('`
            (\t|    )
            (private)*[ \t]*?
            (?P<type>[\w<>,]+)[ \t]+ # type
            (?P<name>[\w]+)[ \t]*?;  # name
            `isx', $classContent, $matches);
        foreach ( $matches['name'] as $index => $protoName ) {
            if ( 'return' == $matches['type'][$index] ) {
                continue;
            }
            $this->classInfo['property'][$protoName] = array(
                'name' => $protoName,
                'type' => $matches['type'][$index],
            );
        }
        
        # match methods
        $methods = array();
        preg_match_all('`
            \n(\t|    ) # make sure it is a comment for method
            ((?P<docComment>/\*.*?\*/)|(?P<normalComment>//.*?\n)+) # Comments over method, like /**/ or //
            (?P<marks>(\s*@.*?\n)*) # Marks over method
            \s*public\s+(?P<returnType>.*?)\s+(?P<methodName>\w+)\s*\(.*?\) # Class defination 
            (?P<throw>.*?) # method throw
            \{(?P<methodContent>.*?)(\t|    )\} #method content
            `isx', $classContent, $matches);
        foreach ( $matches['methodName'] as $index => $methodName ) {
            $method = array();
            $method['name'] = $methodName;
            $method['return'] = $matches['returnType'][$index];
            $method['docComment'] = $matches['docComment'][$index];
            $method['normalComment'] = $matches['normalComment'][$index];
            $method['marks'] = $matches['marks'][$index];
            $method['throw'] = $matches['throw'][$index];
            $method['content'] = $matches['methodContent'][$index];
            
            if ( empty($method['docComment']) ) {
                $method['description'] = trim($method['normalComment'], " \t\n\r\0\x0B/");
            } else {
                $method['description'] = trim($method['docComment'], " \t\n\r\0\x0B/*");
                if ( false !== strpos($method['description'], "\n") ) {
                    $method['description'] = trim(substr($method['description'], 0, strpos($method['description'], "\n")));
                }
            }
            $methods[$methodName] = $method;
        }
        $this->classInfo['method'] = $methods;
    }
    
    /**
     * @param unknown $name
     * @param unknown $attr
     * @return mixed
     */
    public function getMethodAttr( $name, $attr ) {
        if ( !isset($this->classInfo['method'][$name]) ) {
            return null;
        }
        return $this->classInfo['method'][$name][$attr];
    }
    
    /** @return array  */
    public function getMethodCallChain( $name ) {
        $content = $this->getMethodAttr($name, 'content');
        
        $chain = array();
        preg_match_all('`(?P<var>\w+)\.(?P<name>\w+)\s*\(`is', $content, $matches);
        foreach ( $matches['var'] as $index => $varName ){
            $chain[$varName] = $matches['name'][$index];
        }
        return $chain;
    }
    
    /** @return mixed */
    public function getClassInfo( $name ) {
        return $this->classInfo[$name];
    }
}