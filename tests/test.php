<?php

use plugin\account\service\Account;
use plugin\payment\service\Payment;
use think\facade\Db;

include_once '../vendor/autoload.php';
include_once '../vendor/topthink/framework/src/helper.php';

Db::setConfig([
    'default'     => 'mysql',
    'connections' => [
        'mysql' => [
            'type'     => 'mysql',
            'hostname' => '127.0.0.1',
            'database' => 'admin_v6',
            'username' => 'admin_v6',
            'password' => 'FbYBHcWKr2',
            'hostport' => '3306',
            'charset'  => 'utf8mb4',
            'debug'    => true,
        ],
    ],
]);

// 获取指定接口支持的支付类型
$all = Payment::getTypeByChannel(Account::WXAPP);
dump($all);

// 获取全部可用的对付
$all = Payment::getTypeAll();
dump($all);