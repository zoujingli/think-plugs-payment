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

namespace plugin\payment\support\payment;

use plugin\payment\model\DataUserBalance;
use plugin\payment\model\ShopOrder;
use plugin\payment\service\UserBalanceService;
use plugin\payment\support\contract\PaymentAbstract;
use think\admin\Exception;
use think\admin\extend\CodeExtend;

/**
 * 账号余额支付通道
 * Class Balance
 * @package plugin\payment\support\payment
 */
class Balance extends PaymentAbstract
{
    /**
     * 初始化支付通道
     * @return $this
     */
    public function init(): Balance
    {
        return $this;
    }

    /**
     * 订单信息查询
     * @param string $orderNo
     * @return array
     */
    public function query(string $orderNo): array
    {
        return [];
    }

    /**
     * 支付通知处理
     * @return string
     */
    public function notify(): string
    {
        return 'SUCCESS';
    }

    /**
     * 创建订单支付参数
     * @param string $openid 用户OPENID
     * @param string $orderNo 交易订单单号
     * @param string $amount 交易订单金额（元）
     * @param string $payTitle 交易订单名称
     * @param string $payRemark 订单订单描述
     * @param string $payReturn 完成回跳地址
     * @param string $payImages 支付凭证图片
     * @return array
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function create(string $openid, string $orderNo, string $amount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        $order = ShopOrder::mk()->where(['order_no' => $orderNo])->find();
        if (empty($order)) throw new Exception("订单不存在");
        if ($order['status'] !== 2) throw new Exception("不可发起支付");
        // 创建支付行为
        $this->createAction($orderNo, $payTitle, $amount);
        // 检查能否支付
        [$total, $count] = UserBalanceService::amount($order['uuid'], [$orderNo]);
        if ($amount > $total - $count) throw new Exception("可抵扣余额不足");
        try {
            // 扣减用户余额
            $this->app->db->transaction(function () use ($order, $amount) {
                // 更新订单余额
                ShopOrder::mk()->where(['order_no' => $order['order_no']])->update([
                    'payment_balance' => $amount,
                ]);
                // 扣除余额金额
                DataUserBalance::mUpdate([
                    'uuid'   => $order['uuid'],
                    'code'   => "KC{$order['order_no']}",
                    'name'   => "账户余额支付",
                    'remark' => "支付订单 {$order['order_no']} 的扣除余额 {$amount} 元",
                    'amount' => -$amount,
                ], 'code');
                // 更新支付行为
                $this->updateAction($order['order_no'], CodeExtend::uniqidDate(20), $amount, '账户余额支付');
            });
            // 刷新用户余额
            UserBalanceService::amount($order['uuid']);
            return ['code' => 1, 'info' => '余额支付完成'];
        } catch (\Exception $exception) {
            return ['code' => 0, 'info' => $exception->getMessage()];
        }
    }
}