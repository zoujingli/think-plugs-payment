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
use plugin\payment\service\Balance as BalanceService;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentResponse;
use plugin\payment\service\contract\PaymentUsageTrait;
use plugin\payment\service\Payment;
use think\admin\Exception;
use think\Response;

/**
 * 账户余额支付方式
 * @class BalancePayment
 * @package plugin\payment\service\payment
 */
class BalancePayment implements PaymentInterface
{
    use PaymentUsageTrait;

    /**
     * 初始化支付通道
     * @return $this
     */
    public function init(): PaymentInterface
    {
        return $this;
    }

    /**
     * 订单信息查询
     * @param string $pcode
     * @return array
     */
    public function query(string $pcode): array
    {
        return [];
    }

    /**
     * 支付通知处理
     * @param array $data
     * @param ?array $notify
     * @return \think\Response
     */
    public function notify(array $data = [], ?array $notify = null): Response
    {
        return response('SUCCESS');
    }

    /**
     * 发起支付退款
     * @param string $pcode 支付单号
     * @param string $amount 退款金额
     * @param string $reason 退款原因
     * @return array [状态, 消息]
     */
    public function refund(string $pcode, string $amount, string $reason = ''): array
    {
        try {
            // 同步已退款状态
            $this->app->db->transaction(static function () use ($pcode, $amount, $reason) {
                // 记录退款
                $record = static::syncRefund($pcode, $rcode, $amount, $reason);
                // 退回余额
                $remark = "来自订单 {$record->getAttr('order_no')} 退回余额";
                BalanceService::create($record->getAttr('unid'), $rcode, '账号余额退款', floatval($amount), $remark, true);
            });
            return [1, '发起退款成功！'];
        } catch (Exception $exception) {
            return [$exception->getCode(), $exception->getMessage()];
        } catch (\Exception $exception) {
            return [0, $exception->getMessage()];
        }
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
     * @return PaymentResponse
     * @throws \think\admin\Exception
     */
    public function create(AccountInterface $account, string $orderNo, string $orderTitle, string $orderAmount, string $payAmount, string $payRemark = '', string $payReturn = '', string $payImages = ''): PaymentResponse
    {
        try {
            $this->checkLeaveAmount($orderNo, $payAmount, $orderAmount);
            [$data, $unid, $payCode] = [[], $this->withUserUnid($account), Payment::withPaymentCode()];
            $this->app->db->transaction(static function () use (&$data, $unid, $orderNo, $orderTitle, $orderAmount, $payCode, $payAmount, $payRemark) {
                // 检查能否支付
                $data = BalanceService::recount($unid);
                if ($payAmount > $data['usable']) throw new Exception('账户余额不足');
                // 创建支付行为
                $this->createAction($orderNo, $orderTitle, $orderAmount, $payCode, $payAmount, '', $payAmount);
                // 扣除余额金额
                $payRemark = $payRemark ?: "支付订单 {$orderNo} 金额 {$payAmount} 元";
                BalanceService::create($unid, "ZF{$payCode}", $orderTitle, -floatval($payAmount), $payRemark, true);
                // 更新支付行为
                $data = $this->updateAction($payCode, "ZF{$payCode}", $payAmount, '账户余额支付');
            });
            // 刷新用户余额
            BalanceService::recount($unid);
            // 返回支付结果
            return PaymentResponse::mk(true, '余额支付完成！', $data);
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }
}