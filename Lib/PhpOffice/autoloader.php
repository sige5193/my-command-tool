<?php
spl_autoload_register(function( $class ) {
    if ( 'PhpOffice\\' !== substr($class, 0, strlen('PhpOffice\\')) ) {
        return;
    }
    
    $class = str_replace('PhpOffice\\', '', $class);
    $filepath = dirname(__FILE__).DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $class)
            . '.php';
    if ( file_exists($filepath) ) {
        require $filepath;
    }
});