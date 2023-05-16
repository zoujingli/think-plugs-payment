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
use plugin\payment\service\payment\Wechat;
use think\admin\Exception;
use think\Response;
use WePay\Order;

/**
 * 微信支付 V2 版本
 * @class WechatV2
 * @package plugin\payment\service\payment\wechat
 */
class WechatV2 extends Wechat
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
                'body'             => $body,
                'openid'           => $this->withUserField($account, 'openid'),
                'attach'           => $this->cfgCode,
                'out_trade_no'     => $orderNo,
                'trade_type'       => static::tradeTypes[$this->cfgType] ?? '',
                'total_fee'        => $payAmount * 100,
                'notify_url'       => $this->withNotifyUrl($orderNo),
                'spbill_create_ip' => $this->app->request->ip(),
            ];
            if (empty($data['openid'])) unset($data['openid']);
            $info = $this->payment->create($data);
            if ($info['return_code'] === 'SUCCESS' && $info['result_code'] === 'SUCCESS') {
                // 创建支付记录
                $this->createAction($orderNo, $payTitle, $payAmount);
                // 返回支付参数
                return $this->payment->jsapiParams($info['prepay_id']);
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
     * @param string $orderno 订单单号
     * @return array
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    public function query(string $orderno): array
    {
        $result = $this->payment->query(['out_trade_no' => $orderno]);
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
}