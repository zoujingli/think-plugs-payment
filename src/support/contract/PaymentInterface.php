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

namespace plugin\payment\support\contract;

/**
 * 支付通道接口类
 * @class PaymentInterface
 * @package plugin\payment\support\contract
 */
interface PaymentInterface
{

    /**
     * 主动查询订单支付
     * @param string $orderNo
     * @return array
     */
    public function query(string $orderNo): array;

    /**
     * 支付通知处理
     * @return string
     */
    public function notify(): string;

    /**
     * 创建支付订单
     * @param string $openid 用户OPENID
     * @param string $orderNo 交易订单单号
     * @param string $amount 交易订单金额（元）
     * @param string $payTitle 交易订单名称
     * @param string $payRemark 交易订单描述
     * @param string $payReturn 支付回跳地址
     * @param string $payImages 支付凭证图片
     * @return array
     */
    public function create(string $openid, string $orderNo, string $amount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array;

}