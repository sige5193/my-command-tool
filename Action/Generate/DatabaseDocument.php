<?php
namespace Action\Generate;
use Core\CommandActionAbstract;
use Core\OhaCore;
class DatabaseDocument extends CommandActionAbstract {
    /**
     * 产品业务中心数据库文档生成
     * @param string $tables 英文逗号分隔的表名列表
     */
    protected function run( $tables ) {
        $tableNames = explode(',', $tables);
        $config = $this->getFormattedConfig();
        $tableComments = $config['TableComment'];
        $tablePrimaryKeys = $config['TablePrimaryKeyMap'];
        $tableColumns = $config['TableColumns'];
        $tableColumnComments = $config['TableColumnComment'];
        
        $tables = array();
        foreach ( $tableNames as $tableName ) {
            $this->info("process table `{$tableName}`");
            $table = array();
            $table['name'] = $tableName;
            $table['chinese'] = $tableComments[$tableName];
            $table['primaryKey'] = $tablePrimaryKeys[$tableName];
            $table['columns'] = array();
            foreach ( $tableColumns as $columnInfo ) {
                if ( $columnInfo['TABLE_NAME'] !== $tableName ) {
                    continue;
                }
                
                $column = array();
                $column['name'] = $columnInfo['COLUMN_NAME'];
                $columnKey = $tableName."::".$columnInfo['COLUMN_NAME'];
                $column['chinese'] = trim($tableColumnComments[$columnKey]);
                $column['isPrimaryKey'] = $columnInfo['COLUMN_NAME']==$table['primaryKey'] ? 'PK' : '';
                $column['type'] = $columnInfo['DATA_TYPE'];
                $column['length'] = $columnInfo['DATA_LENGTH'];
                $column['notNull'] = 'N'==$columnInfo['NULLABLE'] ? 'NOT NULL' : 'NULL';
                $column['default'] = trim($columnInfo['DATA_DEFAULT']);
                $table['columns'][] = $column;
            }
            $tables[] = $table;
        }
        
        $html = $this->renderView('Data/Template/DatabaseDoc.php', array('tables'=>$tables));
        file_put_contents('db-doc.html', $html);
    }
    
    /**
     * @return array
     */
    private function getFormattedConfig( ) {
        $newConfig = array();
        
        $config = OhaCore::system()->getConfig();
        $tableComments = $config['ShgtSiteAdmin']['DataBaseTableInfo']['TableComment']['RECORDS'];
        foreach ( $tableComments as $index => $tableComment ) {
            $tableComments[$tableComment['TABLE_NAME']] = $tableComment['COMMENTS'];
            unset($tableComments[$index]);
        }
        $newConfig['TableComment'] = $tableComments;
        
        $tablePrimaryKeys = $config['ShgtSiteAdmin']['DataBaseTableInfo']['TablePrimaryKeyMap']['RECORDS'];
        foreach ( $tablePrimaryKeys as $index => $tablePrimaryKey ) {
            $tablePrimaryKeys[$tablePrimaryKey['TABLE_NAME']] = $tablePrimaryKey['COLUMN_NAME'];
            unset($tablePrimaryKeys[$index]);
        }
        $newConfig['TablePrimaryKeyMap'] = $tablePrimaryKeys;
        
        $tableColumns = $config['ShgtSiteAdmin']['DataBaseTableInfo']['TableColumns']['RECORDS'];
        foreach ( $tableColumns as $index => $tableColumn ) {
            $newKey = $tableColumn['TABLE_NAME']."::".$tableColumn['COLUMN_NAME'];
            $tableColumns[$newKey] = $tableColumn;
            unset($tableColumns[$index]);
        }
        $newConfig['TableColumns'] = $tableColumns;
        
        $tableColumnComments = $config['ShgtSiteAdmin']['DataBaseTableInfo']['TableColumnComment']['RECORDS'];
        foreach ( $tableColumnComments as $index => $tableColumnComment ) {
            $newKey = $tableColumnComment['TABLE_NAME']."::".$tableColumnComment['COLUMN_NAME'];
            $tableColumnComments[$newKey] = $tableColumnComment['COMMENTS'];
            unset($tableColumnComments[$index]);
        }
        $newConfig['TableColumnComment'] = $tableColumnComments;
        return $newConfig;
    }
}