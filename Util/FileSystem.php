<?php
namespace Util;
class FileSystem {
    /** @return array */
    public static function fetchDirFiles( $path ) {
        if ( is_file($path) ) {
            return array($path);
        }
    
        $path = trim($path, DIRECTORY_SEPARATOR);
        $files = array();
        $subFiles = scandir($path);
        foreach ( $subFiles as $subFile ) {
            if ( '.' === $subFile[0] ) {
                continue;
            }
            $subPath = $path.DIRECTORY_SEPARATOR.$subFile;
            if ( is_dir($subPath) ) {
                $files = array_merge($files, self::fetchDirFiles($subPath));
            } else {
                $files[] = $subPath;
            }
        }
        return $files;
    }
}