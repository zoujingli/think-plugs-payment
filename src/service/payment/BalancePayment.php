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
use plugin\payment\model\PluginPaymentRefund;
use plugin\payment\service\Balance;
use plugin\payment\service\Balance as BalanceService;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentUsageTrait;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
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
     * @param array|null $data
     * @return \think\Response
     */
    public function notify(?array $data = []): Response
    {
        return response('SUCCESS');
    }

    /**
     * 子支付单退款
     * @param string $pcode 子支付单号
     * @param string $amount 退款金额（元）
     * @return array
     * @throws \think\admin\Exception
     */
    public function refund(string $pcode, string $amount): array
    {
        $pay = $this->checkRefund($pcode, $amount);
        $code = CodeExtend::uniqidNumber(16, 'R');
        $model = PluginPaymentRefund::mk()->whereRaw('1<>1')->findOrEmpty();
        $this->app->db->transaction(function () use ($pay, $code, &$model, $amount) {
            $model->save(array_merge($pay->toArray(), [
                'code'          => $code,
                'record_code'   => $pay->getAttr('code'),
                'refund_amount' => $amount,
                'refund_status' => 1,
                'refund_time'   => date('Y-m-d H:i:s'),
                'used_balance'  => $amount
            ]));
            Balance::create($pay->getAttr('unid'), $code, "来自订单 {$pay->getAttr('order_no')} 退回余额", floatval($amount), '', true);
            // 刷新退款金额
            $refundAmount = PluginPaymentRefund::mk()->where(['record_code' => $pay->getAttr('code')])->sum('refund_amount');
            $pay->save(['refund_status' => 1, 'refund_amount' => $refundAmount]);
        });
        return $model->toArray();
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
            $this->checkLeaveAmount($orderNo, $payAmount, $orderAmount);
            [$data, $unid, $payCode] = [[], $this->withUserUnid($account), $this->withPayCode()];
            $this->app->db->transaction(function () use (&$data, $unid, $orderNo, $orderTitle, $orderAmount, $payCode, $payAmount, $payRemark) {
                // 检查能否支付
                $data = BalanceService::recount($unid);
                if ($payAmount > $data['usable']) throw new Exception('可抵扣的余额不足');
                // 创建支付行为
                $this->createAction($orderNo, $orderTitle, $orderAmount, $payCode, $payAmount, '', $payAmount);
                // 扣除余额金额
                BalanceService::create($unid, $orderNo, $orderTitle, -floatval($payAmount), $payRemark, true);
                // 更新支付行为
                $data = $this->updateAction($payCode, CodeExtend::uniqidDate(20), $payAmount, '账户余额支付');
            });
            // 刷新用户余额
            BalanceService::recount($unid);
            // 返回支付结果
            return ['code' => 1, 'info' => '余额支付完成', 'data' => $data, 'param' => []];
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }
}