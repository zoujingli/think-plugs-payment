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

namespace plugin\payment\service\contract;

use plugin\payment\model\PluginPaymentAction;
use plugin\payment\service\Payment;
use think\admin\Exception;
use think\App;

/**
 * 支付通道抽像类
 * @class PaymentAbstract
 * @package plugin\payment\service\contract
 */
abstract class PaymentAbstract implements PaymentInterface
{
    /**
     * 当前应用对象
     * @var \think\App
     */
    protected $app;

    /**
     * 支付调度参数
     * @var array
     */
    protected $config;

    /**
     * 支付通道编号
     * @var string
     */
    protected $cfgCode;

    /**
     * 支付通道类型
     * @var string
     */
    protected $cfgType;

    /**
     * 支付通道参数
     * @var array
     */
    protected $cfgParams;

    /**
     * 支付发起类型
     * @var string
     */
    protected $tradeType;

    /**
     * 支付通道构造函数
     * @param \think\App $app
     * @param string $code
     * @param string $type
     * @param array $params
     * @throws \think\admin\Exception
     */
    public function __construct(App $app, string $code, string $type, array $params)
    {
        $this->app = $app;
        $this->cfgCode = $code;
        $this->cfgType = $type;
        $this->cfgParams = $params;

        if (isset(Payment::types[$this->cfgType])) {
            $this->tradeType = Payment::types[$this->cfgType]['type'];
            $this->init();
        } else {
            throw new Exception(sprintf('支付类型[%s]未配置定义！', $this->cfgType));
        }
    }

    /**
     * 初始化支付通道
     * @return PaymentInterface
     */
    abstract public function init(): PaymentInterface;

    /**
     * 创建支付行为
     * @param string $orderNo 商户订单单号
     * @param string $payTitle 商户订单标题
     * @param string $payAmount 需要支付金额
     */
    protected function createAction(string $orderNo, string $payTitle, string $payAmount)
    {
        PluginPaymentAction::mk()->insert([
            'payment_code' => $this->cfgCode,
            'payment_type' => $this->cfgType,
            'order_no'     => $orderNo,
            'order_name'   => $payTitle,
            'order_amount' => $payAmount,
        ]);
    }

    /**
     * 更新支付记录并更新订单
     * @param string $orderno 商户订单单号
     * @param string $payTrade 平台交易单号
     * @param string $payAmount 实际到账金额
     * @param string $payRemark 平台支付备注
     * @return boolean
     */
    protected function updateAction(string $orderno, string $payTrade, string $payAmount, string $payRemark = '在线支付'): bool

    {
        // 更新支付记录
        PluginPaymentAction::mUpdate([
            'order_no'         => $orderno,
            'payment_code'     => $this->cfgCode,
            'payment_type'     => $this->cfgType,
            'payment_trade'    => $payTrade,
            'payment_amount'   => $payAmount,
            'payment_status'   => 1,
            'payment_datetime' => date('Y-m-d H:i:s'),
        ], 'order_no', [
            'payment_code' => $this->cfgCode,
            'payment_type' => $this->cfgType,
        ]);
        // 更新记录状态
        return $this->updateOrder($orderno, $payTrade, $payAmount, $payRemark);
    }

    /**
     * 订单支付更新操作
     * @param string $orderNo 订单单号
     * @param string $payTrade 交易单号
     * @param string $payAmount 支付金额
     * @param string $payRemark 支付描述
     * @param string $payImage 支付凭证
     * @return boolean
     */
    protected function updateOrder(string $orderNo, string $payTrade, string $payAmount, string $payRemark = '在线支付', string $payImage = ''): bool
    {
        $map = ['status' => 2, 'order_no' => $orderNo, 'payment_status' => 0];
        $order = ShopOrder::mk()->where($map)->findOrEmpty();
        if ($order->isEmpty()) return false;
        // 检查订单支付状态
        if ($this->cfgType === Payment::VOUCHER) {
            $status = 3; # 凭证支付需要审核
        } elseif (empty($order['truck_type'])) {
            $status = 6; # 虚拟订单直接完成
        } else {
            $status = 4; # 实物订单需要发货
        }
        // 更新订单支付状态
        $order['status'] = $status;
        $order['payment_code'] = $this->cfgCode;
        $order['payment_type'] = $this->cfgType;
        $order['payment_trade'] = $payTrade;
        $order['payment_image'] = $payImage;
        $order['payment_amount'] = $payAmount;
        $order['payment_remark'] = $payRemark;
        $order['payment_status'] = 1;
        $order['payment_datetime'] = date('Y-m-d H:i:s');
        $order->save();
        // 触发订单更新事件
        if ($status >= 4) {
            $this->app->event->trigger('ShopOrderPayment', $orderNo);
        }
        return true;
    }
}