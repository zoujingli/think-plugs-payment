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

namespace plugin\payment\service\payment\wechat;

use plugin\account\service\contract\AccountInterface;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentUsageTrait;
use plugin\payment\service\Payment;
use plugin\payment\service\payment\Wechat;
use think\admin\Exception;
use think\Response;
use WePayV3\Order;

/**
 * 微信支付 V3 版本
 * @class WechatV3
 * @package plugin\payment\service\payment\wechat
 */
class WechatV3 extends Wechat
{
    use PaymentUsageTrait;

    /** @var \WePayV3\Order */
    private $payment;

    /**
     * 支付通道初始化
     * @return PaymentInterface
     */
    public function init(): PaymentInterface
    {
        parent::init();
        $this->payment = Order::instance($this->config);
        return $this;
    }

    /**
     * 创建订单支付参数
     * @param AccountInterface $account
     * @param string $orderNo 交易订单单号
     * @param string $payAmount 交易订单金额（元）
     * @param string $payTitle 交易订单名称
     * @param string $payRemark 订单订单描述
     * @param string $payReturn 完成回跳地址
     * @param string $payImages 支付凭证图片
     * @return array
     * @throws Exception
     */
    public function create(AccountInterface $account, string $orderNo, string $payAmount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        try {
            $this->withUserUnid($account);
            $body = empty($payRemark) ? $payTitle : ($payTitle . '-' . $payRemark);
            $data = [
                'appid'        => $this->config['appid'],
                'mchid'        => $this->config['mch_id'],
                'payer'        => ['openid' => $this->withUserField($account, 'openid')],
                'amount'       => ['total' => intval($payAmount * 100), 'currency' => 'CNY'],
                'notify_url'   => $this->withNotifyUrl($orderNo),
                'description'  => $body,
                'out_trade_no' => $orderNo,
            ];
            $tradeType = static::tradeTypes[$this->cfgType] ?? '';
            if (in_array($this->cfgType, [Payment::WECHAT_WAP, Payment::WECHAT_QRC])) {
                unset($data['payer']);
            }
            if ($this->cfgType === Payment::WECHAT_WAP) {
                $tradeType = 'h5';
                $data['scene_info'] = ['h5_info' => ['type' => 'Wap'], 'payer_client_ip' => request()->ip()];
            }
            $info = $this->payment->create(strtolower($tradeType), $data);
            // 创建支付记录
            $this->createAction($orderNo, $payTitle, $payAmount);
            // 返回支付参数
            return $info;
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 查询微信支付订单
     * @param string $orderno 订单单号
     * @return array
     */
    public function query(string $orderno): array
    {
        try {
            $result = $this->payment->query($orderno);
            if (isset($result['trade_state']) && $result['trade_state'] === 'SUCCESS') {
                $this->updateAction($result['out_trade_no'], strval($result['amount']['total'] / 100), $result['transaction_id'] ?? '');
            }
            return $result;
        } catch (\Exception $exception) {
            return ['trade_state' => 'ERROR', 'trade_state_desc' => $exception->getMessage()];
        }
    }

    /**
     * 支付结果处理
     * @param array|null $data
     * @return \think\Response
     */
    public function notify(?array $data = null): Response
    {
        try {
            $notify = $data ?: $this->payment->notify();
            if (($result = $notify['result'] ?? []) && isset($result['trade_state']) && $result['trade_state'] == 'SUCCESS') {
                if ($this->updateAction($result['out_trade_no'], $result['transaction_id'], strval($result['amount']['payer_total'] / 100))) {
                    return response('success');
                }
                return json(['code' => 'FAIL', 'message' => 'Failed to modify order status.'])->code(500);
            } else {
                return response('success');
            }
        } catch (\Exception $exception) {
            return json(['code' => 'FAIL', 'message' => $exception->getMessage()])->code(500);
        }
    }
}