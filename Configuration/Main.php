<?php
return array(
'CharSet' => array(
    'Input' => 'GB2312',
    'Output' => 'GB2312//IGNORE',
),
'ShgtSiteAdmin' => array(
    'MenuItem' => json_decode(file_get_contents(__DIR__.'/shgt-site-admin-menu-item.json'), true),
    'DataBaseTableInfo' => array(
        'TablePrimaryKeyMap' => json_decode(file_get_contents(__DIR__.'/db_table_primary_key.json'), true),
        'TableComment' => json_decode(file_get_contents(__DIR__.'/db_table_comment.json'), true),
        'TableColumns' => json_decode(file_get_contents(__DIR__.'/db_column_base.json'), true),
        'TableColumnComment' => json_decode(file_get_contents(__DIR__.'/db_column_comment.json'), true),
    ),
),
);