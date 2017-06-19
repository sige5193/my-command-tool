<?php
spl_autoload_register(function( $class ) {
    if ( 'Facebook\\WebDriver\\' !== substr($class, 0, strlen('Facebook\\WebDriver\\')) ) {
        return;
    }
    
    $class = str_replace('Facebook\\WebDriver\\', '', $class);
    $filepath = dirname(__FILE__).DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $class)
            . '.php';
    if ( file_exists($filepath) ) {
        require $filepath;
    }
});