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
use plugin\payment\model\PluginPaymentRecord;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\payment\WechatPayment;
use think\admin\Exception;
use think\Response;
use WePay\Order;
use WePay\Refund;

/**
 * 微信支付 V2 版本
 * @class WechatPaymentV2
 * @package plugin\payment\service\payment\wechat
 */
class WechatPaymentV2 extends WechatPayment
{
    /** @var Order */
    private $payment;

    /**
     * 初始化支付通道
     * @return PaymentInterface
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
     * @return array
     * @throws \think\admin\Exception
     */
    public function create(AccountInterface $account, string $orderNo, string $orderTitle, string $orderAmount, string $payAmount, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        try {
            [$payCode] = [$this->withPayCode(), $this->withUserUnid($account)];
            $body = empty($orderRemark) ? $orderTitle : ($orderTitle . '-' . $orderRemark);
            $data = [
                'body'             => $body,
                'openid'           => $this->withUserField($account, 'openid'),
                'attach'           => $this->cfgCode,
                'out_trade_no'     => $payCode,
                'trade_type'       => static::tradeTypes[$this->cfgType] ?? '',
                'total_fee'        => $payAmount * 100,
                'notify_url'       => $this->withNotifyUrl($payCode),
                'spbill_create_ip' => $this->app->request->ip(),
            ];
            if (empty($data['openid'])) unset($data['openid']);
            $info = $this->payment->create($data);
            if ($info['return_code'] === 'SUCCESS' && $info['result_code'] === 'SUCCESS') {
                // 支付参数过滤
                $param = isset($info['prepay_id']) ? $this->payment->jsapiParams($info['prepay_id']) : $info;
                // 创建支付记录
                $data = $this->createAction($orderNo, $orderTitle, $orderAmount, $payCode, $payAmount);
                // 返回支付参数
                return ['code' => 1, 'info' => '创建支付成功', 'data' => $data, 'param' => $param];
            }
            throw new Exception($info['err_code_des'] ?? '获取预支付码失败！');
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 查询微信支付订单
     * @param string $pcode 支付号
     * @return array
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    public function query(string $pcode): array
    {
        $result = $this->payment->query(['out_trade_no' => $pcode]);
        if (isset($result['return_code']) && isset($result['result_code']) && isset($result['attach'])) {
            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                $this->updateAction($result['out_trade_no'], strval($result['cash_fee'] / 100), $result['transaction_id']);
            }
        }
        return $result;
    }

    /**
     * 支付结果处理
     * @param array|null $data
     * @return \think\Response
     * @throws \WeChat\Exceptions\InvalidResponseException
     */
    public function notify(?array $data = null): Response
    {
        $notify = $data ?: $this->payment->getNotify();
        if ($notify['result_code'] == 'SUCCESS' && $notify['return_code'] == 'SUCCESS') {
            if ($this->updateAction($notify['out_trade_no'], $notify['transaction_id'], strval($notify['cash_fee'] / 100))) {
                return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
            } else {
                return response('error');
            }
        } else {
            return xml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
        }
    }

    /**
     * 子支付单退款
     * @param string $pcode
     * @param string $amount
     * @return array
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @throws \think\admin\Exception
     * @todo 写退款流程
     */
    public function refund(string $pcode, string $amount): array
    {
        $record = PluginPaymentRecord::mk()->where(['code' => $pcode])->findOrEmpty();
        if ($record->isEmpty()) throw new Exception('');
        // 创建退款申请
        $options = [
            'transaction_id' => '1008450740201411110005820873',
            'out_refund_no'  => '商户退款单号',
            'total_fee'      => '1',
            'refund_fee'     => '1',
        ];
        $result = Refund::instance($this->config)->create($options);
        return [];
    }
}