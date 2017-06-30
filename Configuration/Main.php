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
            'Name' => 'APP1',
            'ClientID' => '1136c15a0aefd011d8b7',
            'ClientSecret'=>'4592cdf9fa8ec2dc8b08cb34fb54e4a01c65b5ec',
        ),
        array(
            'Name' => 'APP2',
            'ClientID' => '7edd98588f7b65c31d67',
            'ClientSecret'=>'a360fabee3b6bfbf9ca0335f33bbacf2972a236e',
        ),
        array(
            'Name' => 'APP3',
            'ClientID' => 'fe609cff255c89fd2931',
            'ClientSecret'=>'a9e8faeb4d5e702fa73d7bb46043276c5ca4283c',
        ),
        array(
            'Name' => 'APP4',
            'ClientID' => '9c78b628a445a49d0917',
            'ClientSecret'=>'cfead9ba87e4f2db310102eb20d4e33b12a9502b',
        ),
        array(
            'Name' => 'APP5',
            'ClientID' => 'cb4b76e9db74f296f489',
            'ClientSecret'=>'41b7d6e852d0cf2cc3de32db207b6c8a680f35ac',
        ),
        array(
            'Name' => 'APP6',
            'ClientID' => '1f8c08956c3106f2d754',
            'ClientSecret'=>'1bf5574f46280e670f076642ef91cc427e6e0c4b',
        ),
        array(
            'Name' => 'APP7',
            'ClientID' => '4bfe0e09f55b2d84d005',
            'ClientSecret'=>'cfef7e67415a6133698b5732bfc181a3ee728385',
        ),
        array(
            'Name' => 'APP8',
            'ClientID' => '0c1180deda152fb7cdfb',
            'ClientSecret'=>'4d8c61f578ed4464e14cd447b4a1130eb28116d2',
        ),
        array(
            'Name' => 'APP9',
            'ClientID' => '5d72680f40f013c03f53',
            'ClientSecret'=>'7e77da55aec242e3de3ce530eacf33d7e31d27f5',
        ),
        array(
            'Name' => 'APP10',
            'ClientID' => '7e326647fde06462cf4a',
            'ClientSecret'=>'4bc7d2c8bd628ff1c5fb14b971ee48e9bc0d5568',
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