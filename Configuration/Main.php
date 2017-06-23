<?php
return array(
'Core' => array(
    'TimeZone' => 'Asia/Shanghai',
),
'CharSet' => array(
    'Input' => 'GB2312',
    'Output' => 'GB2312//IGNORE',
),
'Github' => array(
    'AppInfos' => array(
        array(
            'Name' => 'NoProxy',
            'ClientID' => '1136c15a0aefd011d8b7',
            'ClientSecret'=>'4592cdf9fa8ec2dc8b08cb34fb54e4a01c65b5ec',
            'Proxy' => null,
            'Concurrency' => null,
        ),
        array(
            'Name' => 'RemoteProxy',
            'ClientID' => '7edd98588f7b65c31d67',
            'ClientSecret'=>'a360fabee3b6bfbf9ca0335f33bbacf2972a236e',
            'Proxy' => 'https://121.42.63.89:16816',
            'Concurrency' => 5,
        ),
        array(
            'Name' => 'LocalProxy',
            'ClientID' => 'fe609cff255c89fd2931',
            'ClientSecret'=>'a9e8faeb4d5e702fa73d7bb46043276c5ca4283c',
            'Proxy' => 'https://127.0.0.1:1080',
            'Concurrency' => 3,
        ),
    ),
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