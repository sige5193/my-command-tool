<?php
namespace Action\Suanhetao\WebService;
use Core\CommandActionAbstract;
use Core\Util;
class GenerateModelAndCurd extends CommandActionAbstract {
    /**
     * 根据指定的数据库表生成模型与数据库操作动作。
     * @param string $projectLocation 项目路径
     * @param string $dsn 数据库链接字符串
     * @param string $table 需要处理的表名, 如果为空则标识处理所有表
     * @param string $user 数据库链接用户名
     * @param string $password 数据库链接密码
     * @return void
     */
    public function run ( $projectLocation, $dsn, $table=null, $user=null, $password=null ) {
        if ( null !== $table ) {
            $this->doGenerateByTable($projectLocation, $dsn, $table, $user, $password);
        } else {
            $tables = $this->getTables($dsn, $table, $user, $password);
            foreach ( $tables as $table ) {
                $this->doGenerateByTable($projectLocation, $dsn, $table, $user, $password);
            }
        }
    }
    
    /**
     * @param unknown $projectLocation
     * @param unknown $dsn
     * @param unknown $table
     * @param unknown $user
     * @param unknown $password
     */
    private function doGenerateByTable($projectLocation, $dsn, $table, $user=null, $password=null) {
        $tableInfo = $this->getTableConfig($dsn, $table, $user, $password);
        
        # generate model
        $modelData = array();
        $modelData['tableName'] = $tableInfo['name'];
        $modelData['modelName'] = rtrim(ucfirst($tableInfo['name']), 's');
        $modelData['modelName'] = explode('_', $modelData['modelName']);
        $modelData['modelName'] = array_map('ucfirst', $modelData['modelName']);
        $modelData['modelName'] = implode("", $modelData['modelName']);
        $modelData['attributes'] = array();
        $parentColumns = array('id', 'record_created_at','record_updated_at');
        foreach ( $tableInfo['columns'] as $colName => $colDef ) {
            if ( in_array($colName, $parentColumns) ) {
                continue;
            }
        
            $attrDescription = array();
            $attrDescription[] = strtoupper($colDef['type']);
            if ( !empty($colDef['length']) ) {
                $attrDescription[] = "({$colDef['length']})";
            }
        
            if ( $colDef['isPK'] ) { $attrDescription[] = 'PRIMARY'; }
            if ( $colDef['isUnique'] ) { $attrDescription[] = 'UNIQUE'; }
            if ( $colDef['isNotNull'] ) { $attrDescription[] = 'NOTNULL'; }
            if ( !empty($colDef['default']) ) {
                $attrDescription[] = "[{$colDef['default']}]";
            }
            $modelData['attributes'][$colName] = array(
                'name'=>$colName,
                'comment'=>$colDef['comment'],
                'description'=>implode(' ', $attrDescription)
            );
        }
        $modelPath = rtrim($projectLocation,DIRECTORY_SEPARATOR)
        .str_replace('/', DIRECTORY_SEPARATOR, "/Module/Api/Model/{$modelData['modelName']}.php");
        $modelContent = "<?php \n".$this->renderView('Data/Suanhetao/WebService/TemplateModel.php', $modelData);
        file_put_contents($modelPath, $modelContent);
        Util::printf("Model Path : %s\n", $modelPath);
        
        $actionBasePath = rtrim($projectLocation,DIRECTORY_SEPARATOR).str_replace('/', DIRECTORY_SEPARATOR, "/Module/Api/Action/{$modelData['modelName']}/");
        if ( !is_dir($actionBasePath) ) {
            mkdir($actionBasePath, 0777, true);
        }
        $actionData = array('modelName'=>$modelData['modelName'], 'attributes'=>array_keys($modelData['attributes']));
        
        # Action Add
        $actionPath =str_replace('/', DIRECTORY_SEPARATOR, "{$actionBasePath}Add.php");
        $actionContent = "<?php \n".$this->renderView('Data/Suanhetao/WebService/TemplateActionAdd.php', $actionData);
        file_put_contents($actionPath, $actionContent);
        Util::printf("Action Add Path : %s\n", $actionPath);
        
        # Action Update
        $actionPath =str_replace('/', DIRECTORY_SEPARATOR, "{$actionBasePath}Update.php");
        $actionContent = "<?php \n".$this->renderView('Data/Suanhetao/WebService/TemplateActionUpdate.php', $actionData);
        file_put_contents($actionPath, $actionContent);
        Util::printf("Action Update Path : %s\n", $actionPath);
        
        # Action Query
        $actionPath =str_replace('/', DIRECTORY_SEPARATOR, "{$actionBasePath}Query.php");
        $actionContent = "<?php \n".$this->renderView('Data/Suanhetao/WebService/TemplateActionQuery.php', $actionData);
        file_put_contents($actionPath, $actionContent);
        Util::printf("Action Query Path : %s\n", $actionPath);
        
        # Action Delete
        $actionPath =str_replace('/', DIRECTORY_SEPARATOR, "{$actionBasePath}Delete.php");
        $actionContent = "<?php \n".$this->renderView('Data/Suanhetao/WebService/TemplateActionDelete.php', $actionData);
        file_put_contents($actionPath, $actionContent);
        Util::printf("Action Delete Path : %s\n", $actionPath);
        
        # Action Review
        $actionPath =str_replace('/', DIRECTORY_SEPARATOR, "{$actionBasePath}Review.php");
        $actionContent = "<?php \n".$this->renderView('Data/Suanhetao/WebService/TemplateActionReview.php', $actionData);
        file_put_contents($actionPath, $actionContent);
        Util::printf("Action Review Path : %s\n", $actionPath);
    }
    
    /**
     * @param unknown $dsn
     * @param unknown $table
     * @param unknown $user
     * @param unknown $password
     */
    private function getTableConfig( $dsn, $table, $user, $password ) {
        $dbType = substr($dsn, 0, strpos($dsn, ':'));
        
        switch ( $dbType ) {
        case 'mysql' :
            $connection = new \PDO($dsn, $user, $password);
            $connection->exec("SET NAMES UTF8");
            $columns = $connection->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='{$table}'")->fetchAll(\PDO::FETCH_ASSOC);
            $tableInfo = array(
                'name' => $table,
                'columns' => array(),
            );
            foreach ( $columns as $column ) {
                $tableInfo['columns'][$column['COLUMN_NAME']] = array(
                    'name' => $column['COLUMN_NAME'],
                    'isPK' => 'PRI' === $column['COLUMN_KEY'],
                    'isUnique' => 'PRI' === $column['COLUMN_KEY'],
                    'isNotNull' => 'NO' === $column['IS_NULLABLE'],
                    'type' => $column['DATA_TYPE'],
                    'length' => $column['CHARACTER_MAXIMUM_LENGTH'],
                    'default' => $column['COLUMN_DEFAULT'],
                    'comment' => $column['COLUMN_COMMENT'],
                );
            }
            return $tableInfo;
        default: throw new \Exception("Unkonwn database type `{$dbType}`.");
        }
    }
    
    /**
     * @param unknown $dsn
     * @param unknown $table
     * @param unknown $user
     * @param unknown $password
     * @throws \Exception
     */
    private function getTables($dsn, $table, $user, $password) {
        $dbType = substr($dsn, 0, strpos($dsn, ':'));
        
        switch ( $dbType ) {
            case 'mysql' :
                $connection = new \PDO($dsn, $user, $password);
                $connection->exec("SET NAMES UTF8");
                $tables = $connection->query("SHOW TABLES")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ( $tables as $index => $table ) {
                    $tables[$index] = array_pop($table);
                }
                return $tables;
            default: throw new \Exception("Unkonwn database type `{$dbType}`.");
        }
    }
}