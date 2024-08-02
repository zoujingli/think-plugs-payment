<?php

// +----------------------------------------------------------------------
// | Payment Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2024 ThinkAdmin [ thinkadmin.top ]
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
use plugin\payment\model\PluginPaymentRefund;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentResponse;
use plugin\payment\service\contract\PaymentUsageTrait;
use plugin\payment\service\Payment;
use plugin\payment\service\payment\WechatPayment;
use think\admin\Exception;
use think\Response;
use WePayV3\Order;

/**
 * 微信支付 V3 版本
 * @class WechatPaymentV3
 * @package plugin\payment\service\payment\wechat
 */
class WechatPaymentV3 extends WechatPayment
{
    use PaymentUsageTrait;

    /** @var \WePayV3\Order */
    private $payment;

    /**
     * 支付配置初始化
     * @return PaymentInterface
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    public function init(): PaymentInterface
    {
        parent::init();
        $this->payment = Order::instance($this->config);
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
     * @param string $payCoupon 优惠券编号
     * @return PaymentResponse
     * @throws \think\admin\Exception
     */
    public function create(AccountInterface $account, string $orderNo, string $orderTitle, string $orderAmount, string $payAmount, string $payRemark = '', string $payReturn = '', string $payImages = '', string $payCoupon = ''): PaymentResponse
    {
        try {
            $this->checkLeaveAmount($orderNo, $payAmount, $orderAmount);
            [$payCode] = [Payment::withPaymentCode(), $this->withUserUnid($account)];
            $body = empty($orderRemark) ? $orderTitle : ($orderTitle . '-' . $orderRemark);
            $data = [
                'appid' => $this->config['appid'],
                'mchid' => $this->config['mch_id'],
                'payer' => ['openid' => $this->withUserField($account, 'openid')],
                'amount' => ['total' => intval($payAmount * 100), 'currency' => 'CNY'],
                'notify_url' => $this->withNotifyUrl($payCode),
                'description' => $body,
                'out_trade_no' => $payCode,
            ];
            $tradeType = static::tradeTypes[$this->cfgType] ?? '';
            if (in_array($this->cfgType, [Payment::WECHAT_WAP, Payment::WECHAT_QRC])) {
                unset($data['payer']);
            }
            if ($this->cfgType === Payment::WECHAT_WAP) {
                $tradeType = 'h5';
                $data['scene_info'] = ['h5_info' => ['type' => 'Wap'], 'payer_client_ip' => request()->ip()];
            }
            if ($this->cfgType === Payment::WECHAT_APP) {
                unset($data['payer']);
            }
            // 创建预支付
            $param = $this->payment->create(strtolower($tradeType), $data);
            if ($this->cfgType === Payment::WECHAT_APP) {
                $param = array_change_key_case($param);
            }
            // 创建支付记录
            $this->createAction($orderNo, $orderTitle, $orderAmount, $payCode, $payAmount);
            // 返回支付参数
            return $this->res->set(true, "创建支付成功！", $data, $param);
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 查询微信支付订单
     * @param string $pcode 订单单号
     * @return array
     */
    public function query(string $pcode): array
    {
        try {
            $result = $this->payment->query($pcode);
            if (isset($result['trade_state']) && $result['trade_state'] === 'SUCCESS') {
                $this->updateAction($result['out_trade_no'], $result['transaction_id'] ?? '', strval($result['amount']['total'] / 100));
            }
            return $result;
        } catch (\Exception $exception) {
            return ['trade_state' => 'ERROR', 'trade_state_desc' => $exception->getMessage()];
        }
    }

    /**
     * 支付通知处理
     * @param array $data
     * @param ?array $notify
     * @return \think\Response
     */
    public function notify(array $data = [], ?array $notify = null): Response
    {
        try {
            $notify = $notify ?: $this->payment->notify();
            p($notify, false, 'notify_v3');
            $result = empty($notify['result']) ? [] : json_decode($notify['result'], true);
            if (empty($result) || !is_array($result)) return response('error', 500);
            if ($data['scen'] === 'order' && ($result['trade_state'] ?? '') == 'SUCCESS') {
                // 不考虑支付平台的优惠券金额
                $pAmount = strval($result['amount']['total'] / 100);
                [$pCode, $pTrade] = [$result['out_trade_no'], $result['transaction_id']];
                $pCoupon = strval(($result['amount']['total'] - $result['amount']['payer_total']) / 100);
                if (!$this->updateAction($pCode, $pTrade, $pAmount, null, $pCoupon, $result)) {
                    return response('error', 500);
                }
            } elseif ($data['scen'] === 'refund' && ($result['refund_status'] ?? '') == 'SUCCESS') {
                if ($data['order'] !== $result['out_refund_no']) return response('error', 500);
                $refund = PluginPaymentRefund::mk()->where(['code' => $result['out_refund_no']])->findOrEmpty();
                if ($refund->isEmpty()) return response('error', 500); else $refund->save([
                    'refund_time' => date('Y-m-d H:i:s', strtotime($result['success_time'])),
                    'refund_trade' => $result['refund_id'],
                    'refund_scode' => $result['refund_status'],
                    'refund_status' => 1,
                    'refund_notify' => json_encode($result, 64 | 256),
                    'refund_account' => $result['user_received_account'] ?? '',
                ]);
                static::syncRefund($refund->getAttr('record_code'));
            }
            return response('success');
        } catch (\Exception $exception) {
            return json(['code' => 'FAIL', 'message' => $exception->getMessage()])->code(500);
        }
    }

    /**
     * 发起支付退款
     * @param string $pcode
     * @param string $amount
     * @param string $reason
     * @param ?string $rcode
     * @return array [状态, 消息]
     * @throws \think\admin\Exception
     */
    public function refund(string $pcode, string $amount, string $reason = '', ?string &$rcode = null): array
    {
        try {
            // 记录退款
            if (floatval($amount) <= 0) return [1, '无需退款！'];
            $record = static::syncRefund($pcode, $rcode, $amount, $reason);
            // 发起退款申请
            $options = [
                'out_trade_no' => $pcode,
                'out_refund_no' => $rcode,
                'notify_url' => static::withNotifyUrl($rcode, 'refund'),
                'amount' => [
                    'total' => intval($record->getAttr('payment_amount') * 100),
                    'refund' => intval(floatval($amount) * 100),
                    'currency' => 'CNY'
                ]
            ];
            if (strlen($reason) > 0) $options['reason'] = $reason;
            $result = $this->payment->createRefund($options);
            if (in_array($result['code'] ?? $result['status'], ['SUCCESS', 'PROCESSING'])) {
                return [1, '已提交退款！'];
            } else {
                throw new Exception($result['message'] ?? $result['status'], 0);
            }
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }
}