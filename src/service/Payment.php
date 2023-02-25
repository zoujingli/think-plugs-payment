<?php

// +----------------------------------------------------------------------
// | Payment Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023 Anyon <zoujingli@qq.com>
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

namespace plugin\payment\service;

use plugin\account\service\Account;
use plugin\payment\model\PluginPaymentConfig;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\payment\Alipay;
use plugin\payment\service\payment\Balance;
use plugin\payment\service\payment\Joinpay;
use plugin\payment\service\payment\Nullpay;
use plugin\payment\service\payment\Voucher;
use plugin\payment\service\payment\Wechat;
use think\admin\Exception;

/**
 * 支付高度器
 * Class Payment
 * @package plugin\payment\service
 */
abstract class Payment
{

    // 用户余额支付
    const NULLPAY = 'nullpay';
    const BALANCE = 'balance';
    const VOUCHER = 'voucher';

    // 汇聚支付参数
    const JOINPAY_GZH = 'joinpay_gzh';
    const JOINPAY_XCX = 'joinpay_xcx';

    // 微信商户支付
    const WECHAT_APP = 'wechat_app';
    const WECHAT_GZH = 'wechat_gzh';
    const WECHAT_XCX = 'wechat_xcx';
    const WECHAT_WAP = 'wechat_wap';
    const WECHAT_QRC = 'wechat_qrc';

    // 支付宝支付参数
    const ALIAPY_APP = 'alipay_app';
    const ALIPAY_WAP = 'alipay_wap';
    const ALIPAY_WEB = 'alipay_web';

    // 支付通道配置，不需要的可以注释
    private static $types = [
        // 空支付，金额为零时自动完成支付
        self::NULLPAY     => [
            'name'    => '订单无需支付',
            'class'   => Nullpay::class,
            'status'  => 1,
            'account' => [],
        ],
        // 余额支付，使用账户余额完成支付
        self::BALANCE     => [
            'name'    => '账户余额支付',
            'class'   => Balance::class,
            'status'  => 1,
            'account' => [
                Account::WAP,
                Account::WEB,
                Account::WXAPP,
                Account::WECHAT,
                Account::IOSAPP,
                Account::ANDROID,
            ],
        ],
        // 凭证支付，上传凭证后台审核支付
        self::VOUCHER     => [
            'name'    => '单据凭证支付',
            'class'   => Voucher::class,
            'status'  => 1,
            'account' => [
                Account::WAP,
                Account::WEB,
                Account::WXAPP,
                Account::WECHAT,
                Account::IOSAPP,
                Account::ANDROID,
            ],
        ],
        // 微信支付配置（不需要的直接注释）
        self::WECHAT_WAP  => [
            'name'    => '微信WAP支付',
            'class'   => Wechat::class,
            'status'  => 1,
            'account' => [Account::WAP],
        ],
        self::WECHAT_APP  => [
            'name'    => '微信APP支付',
            'class'   => Wechat::class,
            'status'  => 1,
            'account' => [Account::IOSAPP, Account::ANDROID],
        ],
        self::WECHAT_XCX  => [
            'name'    => '微信小程序支付',
            'class'   => Wechat::class,
            'status'  => 1,
            'account' => [Account::WXAPP],
        ],
        self::WECHAT_GZH  => [
            'name'    => '微信公众号支付',
            'class'   => Wechat::class,
            'status'  => 1,
            'account' => [Account::WECHAT],
        ],
        self::WECHAT_QRC  => [
            'name'    => '微信二维码支付',
            'class'   => Wechat::class,
            'status'  => 1,
            'account' => [Account::WEB],
        ],
        // 支付宝支持配置（不需要的直接注释）
        self::ALIPAY_WAP  => [
            'name'    => '支付宝WAP支付',
            'class'   => Alipay::class,
            'status'  => 1,
            'account' => [Account::WAP],
        ],
        self::ALIPAY_WEB  => [
            'name'    => '支付宝WEB支付',
            'class'   => Alipay::class,
            'status'  => 1,
            'account' => [Account::WEB],
        ],
        self::ALIAPY_APP  => [
            'name'    => '支付宝APP支付',
            'class'   => Alipay::class,
            'status'  => 1,
            'account' => [Account::ANDROID, Account::IOSAPP],
        ],
        // 汇聚支持配置（不需要的直接注释）
        self::JOINPAY_XCX => [
            'name'    => '汇聚小程序支付',
            'class'   => Joinpay::class,
            'status'  => 1,
            'account' => [Account::WXAPP],
        ],
        self::JOINPAY_GZH => [
            'name'    => '汇聚公众号支付',
            'class'   => Joinpay::class,
            'status'  => 1,
            'account' => [Account::WECHAT],
        ],
    ];

    /**
     * 根据配置实例支付服务
     * @param string $code 支付编号或空支付类型
     * @return PaymentInterface
     * @throws \think\admin\Exception
     */
    public static function mk(string $code): PaymentInterface
    {
        if ($code === self::NULLPAY) {
            $vars = ['code' => 'empty', 'type' => 'empty', 'params' => []];
            return app(Nullpay::class, $vars);
        } else {
            [$type, $attr, $params] = self::params($code);
            /** @var PaymentInterface */
            return app($attr['class'], ['code' => $code, 'type' => $type, 'params' => $params]);
        }
    }

    /**
     * 获取支付配置参数
     * @param string $code 支付通道编号
     * @param array $config 支付通道参数
     * @return array [type, attr, params]
     * @throws Exception
     */
    public static function params(string $code, array $config = []): array
    {
        try {
            if (empty($config)) {
                $map = ['code' => $code, 'status' => 1, 'deleted' => 0];
                $config = PluginPaymentConfig::mk()->where($map)->findOrEmpty()->toArray();
            }
            if (empty($config)) {
                throw new Exception("支付通道[#{$code}]参数异常！");
            }
            $params = @json_decode($config['content'], true);
            if (empty($params)) {
                throw new Exception("支付通道[#{$code}]参数无效！");
            }
            if (empty(self::$types[$config['type']]['status'])) {
                throw new Exception("支付通道[@{$config['type']}]未启用！");
            }
            return [$config['type'], self::$types[$config['type']], $params];
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 添加支付类型
     * @param string $type 支付类型
     * @param string $name 支付名称
     * @param string $class 处理机制
     * @param array $account 绑定终端
     * @return array[]
     */
    public static function addType(string $type, string $name, string $class, array $account = []): array
    {
        if (class_exists($class) && in_array(PaymentInterface::class, class_implements($class))) {
            self::$types[$type] = ['name' => $name, 'class' => $class, 'status' => 1, 'account' => $account];
        }
        return self::$types;
    }

    /**
     * 获取支付类型
     * @param array $types 默认返回支付
     * @return array
     */
    public static function getTypeAll(array $types = []): array
    {
        $binds = array_keys(Account::getTypes(1));
        foreach (self::$types as $k => $v) if (isset($v['bind'])) {
            if (array_intersect($v['account'], $binds)) $types[$k] = $v;
        }
        return $types;
    }

    /**
     * 筛选支付通道
     * @param string $account 指定终端
     * @return array
     */
    public static function getTypeByChannel(string $account): array
    {
        $types = [];
        foreach (self::$types as $type => $attr) {
            if ($attr['status'] > 0 && in_array($account, $attr['account'])) {
                $types[$type] = $attr['name'];
            }
        }
        return $types;
    }
}