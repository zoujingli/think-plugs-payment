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

namespace plugin\payment\service;

use plugin\account\service\Account;
use plugin\payment\model\PluginPaymentConfig;
use plugin\payment\model\PluginPaymentRecord;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\payment\Alipay;
use plugin\payment\service\payment\Balance;
use plugin\payment\service\payment\Joinpay;
use plugin\payment\service\payment\Nullpay;
use plugin\payment\service\payment\Voucher;
use plugin\payment\service\payment\Wechat;
use think\admin\Exception;

/**
 * 支付通道调度器
 * @class Payment
 * @package plugin\payment\service
 */
abstract class Payment
{

    // 用户余额支付
    const NULLPAY = 'nullpay';
    const BALANCE = 'balance';
    const VOUCHER = 'voucher';
    const INTEGRAL = 'integral';

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

    // 已禁用的支付方式
    private static $denys = null;
    private static $cakey = 'plugin.payment.denys';

    // 支付方式配置
    private static $types = [
        // 空支付，金额为零时自动完成支付
        self::NULLPAY     => [
            'name'    => '订单无需支付',
            'class'   => Nullpay::class,
            'status'  => 1,
            'account' => [],
        ],
        // 余额支付，使用账户余额支付
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
        // 积分抵扣，使用账户积分抵扣
        self::INTEGRAL    => [
            'name'    => '账户积分抵扣',
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
     * 实例支付通道
     * @param string $code 支付编号或空支付类型
     * @return PaymentInterface
     * @throws \think\admin\Exception
     */
    public static function mk(string $code): PaymentInterface
    {
        if ($code === self::NULLPAY) {
            return Nullpay::make(self::NULLPAY, self::NULLPAY, []);
        } else {
            [$type, $attr, $params] = self::params($code);
            return $attr['class']::make($code, $type, $params);
        }
    }

    /**
     * 初始化数据状态
     * @return array[]
     */
    private static function init(): array
    {
        if (is_null(self::$denys)) try {
            self::$denys = sysdata(self::$cakey);
            foreach (self::$types as $type => &$item) {
                $item['status'] = intval(!in_array($type, self::$denys));
            }
        } catch (\Exception $exception) {
        }
        return self::$types;
    }

    /**
     * 获取支付参数
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

            $params = is_string($config['content']) ? @json_decode($config['content'], true) : $config['content'];
            if (empty($params)) throw new Exception("支付通道[#{$code}]参数无效！");

            if (empty(self::$types[$config['type']]['status'])) {
                throw new Exception("支付通道[@{$config['type']}]未启用！");
            }
            return [$config['type'], self::$types[$config['type']], $params];
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 添加支付方式
     * @param string $type 支付编码
     * @param string $name 支付名称
     * @param string $class 处理机制
     * @param array $account 绑定终端
     * @return array[]
     */
    public static function add(string $type, string $name, string $class, array $account = []): array
    {
        if (class_exists($class) && in_array(PaymentInterface::class, class_implements($class))) {
            self::$types[$type] = ['name' => $name, 'class' => $class, 'status' => 1, 'account' => $account];
        }
        return self::types();
    }

    /**
     * 设置方式状态
     * @param string $type 支付编码
     * @param integer $status 支付状态
     * @return bool
     */
    public static function set(string $type, int $status): bool
    {
        if (isset(self::$types[$type])) {
            self::$types[$type]['status'] = $status;
            return true;
        } else {
            return false;
        }
    }

    /**
     * 保存支付方式
     * @return true|integer
     * @throws \think\admin\Exception
     */
    public static function save()
    {
        self::$denys = [];
        foreach (self::types() as $k => $v) {
            if (empty($v['status'])) self::$denys[] = $k;
        }
        return sysdata(self::$cakey, self::$denys);
    }

    /**
     * 获取支付方式
     * @param ?integer $status
     * @return array
     */
    public static function types(?int $status = null): array
    {
        try {
            [$all, $binds] = [[], array_keys(Account::types(1))];
            foreach (self::init() as $type => $item) {
                if (is_null($status) || $status == $item['status']) {
                    if (array_intersect($item['account'], $binds)) $all[$type] = $item;
                }
            }
            return $all;
        } catch (\Exception $exception) {
            return [];
        }
    }

    /**
     * 筛选支付方式
     * @param string $account 指定终端
     * @param boolean $getfull 读取参数
     * @return array
     */
    public static function typesByAccess(string $account, bool $getfull = false): array
    {
        $types = [];
        foreach (self::types(1) as $type => $attr) {
            if (in_array($account, $attr['account'])) {
                $types[$type] = $attr['name'];
            }
        }
        if ($getfull) {
            $items = [];
            $query = PluginPaymentConfig::mk()->field('type,code,name,cover,content');
            $query->where(['status' => 1, 'deleted' => 0])->whereIn('type', array_keys($types));
            foreach ($query->order('sort desc,id desc')->cursor() as $item) {
                $item['qrcode'] = $item['content']['voucher_qrcode'] ?? '';
                unset($item['content']);
                $items[] = $item->toArray();
            }
            return $items;
        } else {
            return $types;
        }
    }

    /**
     * 读取支付通道
     * @param boolean $allow
     * @return array
     */
    public static function items(bool $allow = false): array
    {
        $map = ['status' => 1, 'deleted' => 0];
        $items = $allow ? ['all' => ['type' => 'all', 'code' => 'all', 'name' => '全部支付']] : [];
        return $items + PluginPaymentConfig::mk()->where($map)->order('sort desc,id desc')->column('type,code,name', 'code');
    }

    /**
     * 获取支付类型名称
     * @param string $type
     * @return string
     */
    public static function typeName(string $type): string
    {
        return self::$types[$type]['name'] ?? $type;
    }


    /**
     * 判断是否完成支付
     * @param string $order_no 原订单号
     * @param string $amount 需要支付金额
     * @return boolean
     */
    public static function isPayed(string $order_no, string $amount): bool
    {
        $map = ['order_no' => $order_no, 'payment_status' => 1];
        $payed = PluginPaymentRecord::mk()->where($map)->sum('payment_amount');
        return $payed >= floatval($amount);
    }
}