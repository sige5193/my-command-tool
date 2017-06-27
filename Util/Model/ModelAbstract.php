<?php
namespace Util\Model;
use ActiveRecord\Model;
use Core\Util;
class ModelAbstract extends Model {
    /**
     * @param array $data
     */
    public static function insertBatch( $data ) {
        $class = get_called_class();
        $templateModel = new $class();
        
        $ids = array();
        foreach ( $data as $index => $item ) {
            $ids[] = $item['id'];
            $data[$item['id']] = $item;
            unset($data[$index]);
        }
        $exists = self::find('all', array('id'=>$ids));
        foreach ( $exists as $existsItem ) {
            if ( isset($data[$existsItem->id]) ) {
                unset($data[$existsItem->id]);
            }
        }
        
        if ( empty($data) ) {
            return ;
        }
        
        $connection = self::connection();
        $attributes = array_keys($templateModel->attributes());
        $values = array();
        foreach ( $data as $item ) {
            $value = array();
            foreach ( $attributes as $name ) {
                if ( !isset($item[$name]) ) {
                    $value[$name] = 'null';
                } else if ( is_numeric($item[$name]) && false===strpos($item[$name], 'e')) {
                    $value[$name] = $item[$name]*1;
                } else {
                    $value[$name] = $connection->escape($item[$name]);
                }
            }
            $value = '('.implode(',', $value).')';
            $values[] = $value;
        }
        $values = implode(',', $values);
        
        $tableName = self::table()->get_fully_qualified_table_name();
        $query = "INSERT INTO {$tableName} VALUES {$values}";
        
        try {
            $connection->query($query);
        } catch ( \ActiveRecord\DatabaseException $e ) {
            echo Util::printf("Error Query (".$e->getMessage()."): ".$query);
            exit();
        }
    }
}