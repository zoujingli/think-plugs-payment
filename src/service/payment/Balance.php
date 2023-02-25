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

namespace plugin\payment\service\payment;

use plugin\account\service\contract\AccountInterface;
use plugin\payment\model\DataUserBalance;
use plugin\payment\model\ShopOrder;
use plugin\payment\service\contract\PaymentAbstract;
use plugin\payment\service\UserBalanceService;
use think\admin\Exception;
use think\admin\extend\CodeExtend;

/**
 * 账户余额支付通道
 * Class Balance
 * @package plugin\payment\service\payment
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
     * @param string $orderno
     * @return array
     */
    public function query(string $orderno): array
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
     * @param string $orderno 交易订单单号
     * @param string $payAmount 交易订单金额（元）
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
    public function create(AccountInterface $account, string $orderno, string $payAmount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        $order = ShopOrder::mk()->where(['order_no' => $orderno])->find();
        if (empty($order)) throw new Exception("订单不存在");
        if ($order['status'] !== 2) throw new Exception("不可发起支付");
        // 创建支付行为
        $this->createAction($orderno, $payTitle, $payAmount);
        // 检查能否支付
        [$total, $count] = UserBalanceService::amount($order['uuid'], [$orderno]);
        if ($payAmount > $total - $count) throw new Exception("可抵扣余额不足");
        try {
            // 扣减用户余额
            $this->app->db->transaction(function () use ($order, $payAmount) {
                // 更新订单余额
                ShopOrder::mk()->where(['order_no' => $order['order_no']])->update([
                    'payment_balance' => $payAmount,
                ]);
                // 扣除余额金额
                DataUserBalance::mUpdate([
                    'uuid'   => $order['uuid'],
                    'code'   => "KC{$order['order_no']}",
                    'name'   => "账户余额支付",
                    'remark' => "支付订单 {$order['order_no']} 的扣除余额 {$payAmount} 元",
                    'amount' => -$payAmount,
                ], 'code');
                // 更新支付行为
                $this->updateAction($order['order_no'], CodeExtend::uniqidDate(20), $payAmount, '账户余额支付');
            });
            // 刷新用户余额
            UserBalanceService::amount($order['uuid']);
            return ['code' => 1, 'info' => '余额支付完成'];
        } catch (\Exception $exception) {
            return ['code' => 0, 'info' => $exception->getMessage()];
        }
    }
}