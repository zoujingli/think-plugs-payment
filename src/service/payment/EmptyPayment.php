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

namespace plugin\payment\service\payment;

use plugin\account\service\contract\AccountInterface;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentUsageTrait;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\Response;

/**
 * 空支付支付方式
 * @class EmptyPayment
 * @package plugin\payment\service\payment
 */
class EmptyPayment implements PaymentInterface
{
    use PaymentUsageTrait;

    /**
     * 初始化支付通道
     * @return PaymentInterface
     */
    public function init(): PaymentInterface
    {
        return $this;
    }

    /**
     * 创建支付订单
     * @param AccountInterface $account 支付账号
     * @param string $orderNo 交易订单单号
     * @param string $orderTitle 交易订单标题
     * @param string $orderAmount 订单支付金额（元）
     * @param string $payAmount 本次交易金额
     * @param string $payRemark 交易订单描述
     * @param string $payReturn 支付回跳地址
     * @param string $payImages 支付凭证图片
     * @return array [code,info,data,param]
     * @throws \think\admin\Exception
     */
    public function create(AccountInterface $account, string $orderNo, string $orderTitle, string $orderAmount, string $payAmount, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        try {
            [$data, $payCode] = [[], $this->withPayCode(), $this->withUserUnid($account)];
            $this->app->db->transaction(function () use (&$data, $orderNo, $orderTitle, $orderAmount, $payCode, $payAmount) {
                $this->createAction($orderNo, $orderTitle, $orderAmount, $payCode, $payAmount);
                $data = $this->updateAction($payCode, CodeExtend::uniqidDate(20), $payAmount, '无需支付');
            });
            return ['code' => 1, 'info' => '订单无需支付', 'data' => $data, 'param' => []];
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 订单主动查询
     * @param string $pcode
     * @return array
     */
    public function query(string $pcode): array
    {
        return [];
    }

    /**
     * 支付通知处理
     * @param array|null $data
     * @return \think\Response
     */
    public function notify(?array $data = null): Response
    {
        return response();
    }

    /**
     * 子支付单退款
     * @param string $pcode
     * @param string $amount
     * @return array
     * @todo 写退款流程
     */
    public function refund(string $pcode, string $amount): array
    {
        return [];
    }
}