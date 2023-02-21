<?php

namespace plugin\payment;

use think\admin\Plugin;

class Service extends Plugin
{
    protected $package = 'zoujingli/think-plugs-payment';

    public static function menu(): array
    {
        return [];
    }
}