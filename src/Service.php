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

/**
 * 插件注册服务
 * @class Service
 * @package plugin\payment
 */
class Service extends Plugin
{
    /**
     * 定义插件名称
     * @var string
     */
    protected $appName = '多端支付管理';

    /**
     * 定义安装包名
     * @var string
     */
    protected $package = 'zoujingli/think-plugs-payment';

    /**
     * 插件服务注册
     * @return void
     */
    public function register(): void
    {
        // 注册支付通知路由
        $this->app->route->any('/plugin-payment-notify/:vars', function (Request $request) {
            try {
                $data = json_decode(CodeExtend::deSafe64($request->param('vars')), true);
                return Payment::mk($data['channel'])->notify($data);
            } catch (\Exception|\Error $exception) {
                return 'Error: ' . $exception->getMessage();
            }
        });
    }

    /**
     * 定义插件菜单
     * @return array
     */
    public static function menu(): array
    {
        $code = app(static::class)->appCode;
        return array_merge(AccountService::menu(), [
            [
                'name' => '支付管理',
                'subs' => [
                    ['name' => '支付通道管理', 'icon' => 'layui-icon layui-icon-user', 'node' => "{$code}/config/index"],
                    ['name' => '账号余额管理', 'icon' => 'layui-icon layui-icon-cellphone', 'node' => "{$code}/balance/index"],
                    ['name' => '账号积分管理', 'icon' => 'layui-icon layui-icon-find-fill', 'node' => "{$code}/integral/index"],
                    ['name' => '支付行为管理', 'icon' => 'layui-icon layui-icon-edge', 'node' => "{$code}/record/index"],
                    ['name' => '支付退款管理', 'icon' => 'layui-icon layui-icon-firefox', 'node' => "{$code}/refund/index"],
                ],
            ]
        ]);
    }
}