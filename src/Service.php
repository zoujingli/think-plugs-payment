<?php

// +----------------------------------------------------------------------
// | Payment Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 会员免费 ( https://thinkadmin.top/vip-introduce )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-payment
// | github 代码仓库：https://github.com/zoujingli/think-plugs-payment
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace plugin\payment;

use plugin\account\Service as AccountService;
use plugin\payment\service\Payment;
use think\admin\extend\CodeExtend;
use think\admin\Plugin;
use think\Request;

class Service extends Plugin
{
    protected $package = 'zoujingli/think-plugs-payment';

    public function register(): void
    {
        // 注册支付通知路由
        $this->app->route->any('/plugin-payment-notify/:vars', function (Request $request) {
            try {
                $data = json_decode(CodeExtend::deSafe64($request->param('vars')), true);
                return Payment::mk($data['code'])->notify();
            } catch (\Exception|\Error $exception) {
                return 'Error: ' . $exception->getMessage();
            }
        });
    }

    public static function menu(): array
    {
        $name = app(static::class)->appName;
        return array_merge(AccountService::menu(), [
            [
                'name' => '支付管理',
                'subs' => [
                    ['name' => '支付通道管理', 'icon' => 'layui-icon layui-icon-user', 'node' => "{$name}/config/index"],
                    ['name' => '账号余额管理', 'icon' => 'layui-icon layui-icon-cellphone', 'node' => "{$name}/balance/index"],
                    ['name' => '支付行为管理', 'icon' => 'layui-icon layui-icon-cellphone', 'node' => "{$name}/record/index"],
                ],
            ]
        ]);
    }
}