<?php

// +----------------------------------------------------------------------
// | Shop-Demo for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023 Anyon <zoujingli@qq.com>
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 会员免费 ( https://thinkadmin.top/vip-introduce )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace plugin\payment\support;

use plugin\payment\model\BaseUserPayment;
use plugin\payment\support\contract\PaymentInterface;
use plugin\payment\support\payment\Alipay;
use plugin\payment\support\payment\Balance;
use plugin\payment\support\payment\Joinpay;
use plugin\payment\support\payment\Nullpay;
use plugin\payment\support\payment\Voucher;
use plugin\payment\support\payment\Wechat;
use think\admin\Exception;

/**
 * 支付高度器
 * Class Payment
 * @package plugin\payment\support
 */
abstract class Payment
{

    // 用户余额支付
    const PAYMENT_NULLPAY = 'nullpay';
    const PAYMENT_BALANCE = 'balance';
    const PAYMENT_VOUCHER = 'voucher';

    // 汇聚支付参数
    const PAYMENT_JOINPAY_GZH = 'joinpay_gzh';
    const PAYMENT_JOINPAY_XCX = 'joinpay_xcx';

    // 微信商户支付
    const PAYMENT_WECHAT_APP = 'wechat_app';
    const PAYMENT_WECHAT_GZH = 'wechat_gzh';
    const PAYMENT_WECHAT_XCX = 'wechat_xcx';
    const PAYMENT_WECHAT_WAP = 'wechat_wap';
    const PAYMENT_WECHAT_QRC = 'wechat_qrc';

    // 支付宝支付参数
    const PAYMENT_ALIAPY_APP = 'alipay_app';
    const PAYMENT_ALIPAY_WAP = 'alipay_wap';
    const PAYMENT_ALIPAY_WEB = 'alipay_web';

    // 支付通道配置，不需要的可以注释
    const types = [
        // 空支付，金额为零时自动完成支付
        self::PAYMENT_NULLPAY     => [
            'type' => 'NULLPAY',
            'name' => '订单无需支付',
            'bind' => [],
        ],
        // 余额支付，使用账号余额完成支付
        self::PAYMENT_BALANCE     => [
            'type' => 'BALANCE',
            'name' => '账号余额支付',
            'bind' => [
                Account::CHANNEL_WAP,
                Account::CHANNEL_WEB,
                Account::CHANNEL_WXAPP,
                Account::CHANNEL_WECHAT,
                Account::CHANNEL_IOSAPP,
                Account::CHANNEL_ANDROID,
            ],
        ],
        // 凭证支付，上传凭证后台审核支付
        self::PAYMENT_VOUCHER     => [
            'type' => 'VOUCHER',
            'name' => '单据凭证支付',
            'bind' => [
                Account::CHANNEL_WAP,
                Account::CHANNEL_WEB,
                Account::CHANNEL_WXAPP,
                Account::CHANNEL_WECHAT,
                Account::CHANNEL_IOSAPP,
                Account::CHANNEL_ANDROID,
            ],
        ],
        // 微信支付配置（不需要的直接注释）
        self::PAYMENT_WECHAT_WAP  => [
            'type' => 'MWEB',
            'name' => '微信WAP支付',
            'bind' => [Account::CHANNEL_WAP],
        ],
        self::PAYMENT_WECHAT_APP  => [
            'type' => 'APP',
            'name' => '微信APP支付',
            'bind' => [Account::CHANNEL_IOSAPP, Account::CHANNEL_ANDROID],
        ],
        self::PAYMENT_WECHAT_XCX  => [
            'type' => 'JSAPI',
            'name' => '微信小程序支付',
            'bind' => [Account::CHANNEL_WXAPP],
        ],
        self::PAYMENT_WECHAT_GZH  => [
            'type' => 'JSAPI',
            'name' => '微信公众号支付',
            'bind' => [Account::CHANNEL_WECHAT],
        ],
        self::PAYMENT_WECHAT_QRC  => [
            'type' => 'NATIVE',
            'name' => '微信二维码支付',
            'bind' => [Account::CHANNEL_WEB],
        ],
        // 支付宝支持配置（不需要的直接注释）
        self::PAYMENT_ALIPAY_WAP  => [
            'type' => '',
            'name' => '支付宝WAP支付',
            'bind' => [Account::CHANNEL_WAP],
        ],
        self::PAYMENT_ALIPAY_WEB  => [
            'type' => '',
            'name' => '支付宝WEB支付',
            'bind' => [Account::CHANNEL_WEB],
        ],
        self::PAYMENT_ALIAPY_APP  => [
            'type' => '',
            'name' => '支付宝APP支付',
            'bind' => [Account::CHANNEL_ANDROID, Account::CHANNEL_IOSAPP],
        ],
        // 汇聚支持配置（不需要的直接注释）
        self::PAYMENT_JOINPAY_XCX => [
            'type' => 'WEIXIN_XCX',
            'name' => '汇聚小程序支付',
            'bind' => [Account::CHANNEL_WXAPP],
        ],
        self::PAYMENT_JOINPAY_GZH => [
            'type' => 'WEIXIN_GZH',
            'name' => '汇聚公众号支付',
            'bind' => [Account::CHANNEL_WECHAT],
        ],
    ];

    /**
     * 根据配置实例支付服务
     * @param string $code 支付配置编号
     * @return PaymentInterface
     * @throws \think\admin\Exception
     */
    public static function mk(string $code): PaymentInterface
    {
        if ($code === 'empty') {
            $vars = ['code' => 'empty', 'type' => 'empty', 'params' => []];
            return app(Nullpay::class, $vars);
        }
        [$type, $params] = self::params($code);
        $vars = ['code' => $code, 'type' => $type, 'params' => $params];
        // 实例化具体支付参数类型
        if (stripos($type, 'balance') === 0) {
            return app(Balance::class, $vars);
        } elseif (stripos($type, 'voucher') === 0) {
            return app(Voucher::class, $vars);
        } elseif (stripos($type, 'alipay_') === 0) {
            return app(Alipay::class, $vars);
        } elseif (stripos($type, 'wechat_') === 0) {
            return app(Wechat::class, $vars);
        } elseif (stripos($type, 'joinpay_') === 0) {
            return app(Joinpay::class, $vars);
        } else {
            throw new Exception("支付通道 [{$type}] 未定义");
        }
    }

    /**
     * 获取支付配置参数
     * @param string $code 支付通道编号
     * @param array $config 支付通道参数
     * @return array [type, params]
     * @throws Exception
     */
    public static function params(string $code, array $config = []): array
    {
        try {
            if (empty($config)) {
                $map = ['code' => $code, 'status' => 1, 'deleted' => 0];
                $config = BaseUserPayment::mk()->where($map)->findOrEmpty()->toArray();
            }
            if (empty($config)) {
                throw new Exception("支付通道[#{$code}]已禁用或不存在！");
            }
            $params = @json_decode($config['content'], true);
            if (empty($params)) {
                throw new Exception("支付通道[#{$code}]配置参数无效！");
            }
            if (empty(static::types[$config['type']])) {
                throw new Exception("支付通道[@{$config['type']}]配置匹配失败！");
            }
            return [$config['type'], $params];
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 获取支付支付名称
     * @param string $type
     * @return string
     */
    public static function name(string $type): string
    {
        return static::types[$type]['name'] ?? $type;
    }

    /**
     * 获取支付类型
     * @param array $types 默认返回支付
     * @return array
     */
    public static function getTypeAll(array $types = []): array
    {
        $binds = array_keys(Account::types);
        foreach (static::types as $k => $v) if (isset($v['bind'])) {
            if (array_intersect($v['bind'], $binds)) $types[$k] = $v;
        }
        return $types;
    }

    /**
     * 筛选可用的支付类型
     * @param string $api 指定接口类型
     * @param array $types 默认返回支付
     * @return array
     */
    public static function getTypeApi(string $api = '', array $types = []): array
    {
        foreach (self::types as $type => $attr) {
            if (in_array($api, $attr['bind'])) $types[] = $type;
        }
        return array_unique($types);
    }
}