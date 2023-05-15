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

use AliPay\App;
use AliPay\Wap;
use AliPay\Web;
use plugin\account\service\contract\AccountInterface;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentUsageTrait;
use plugin\payment\service\Payment;
use think\admin\Exception;
use think\Response;

/**
 * 支付宝支付通道
 * @class Alipay
 * @package plugin\payment\service\payment
 */
class Alipay implements PaymentInterface
{
    use PaymentUsageTrait;

    /**
     * 初始化支付通道
     * @return PaymentInterface
     */
    public function init(): PaymentInterface
    {
        $this->config = [
            // 沙箱模式
            'debug'       => false,
            // 签名类型（RSA|RSA2）
            'sign_type'   => "RSA2",
            // 应用ID
            'appid'       => $this->cfgParams['alipay_appid'],
            // 支付宝公钥 (1行填写，特别注意，这里是支付宝公钥，不是应用公钥，最好从开发者中心的网页上去复制)
            'public_key'  => $this->_trimCert($this->cfgParams['alipay_public_key']),
            // 支付宝私钥 (1行填写)
            'private_key' => $this->_trimCert($this->cfgParams['alipay_private_key']),
            // 支付成功通知地址
            'notify_url'  => '',
            // 网页支付回跳地址
            'return_url'  => '',
        ];
        return $this;
    }

    /**
     * 去除证书内容前后缀
     * @param string $content
     * @return string
     */
    private function _trimCert(string $content): string
    {
        return preg_replace(['/\s+/', '/-{5}.*?-{5}/'], '', $content);
    }

    /**
     * 创建订单支付参数
     * @param AccountInterface $account 用户账号实例
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
            $this->config['notify_url'] = $this->withNotifyUrl($orderNo);
            if (in_array($this->cfgType, [Payment::ALIPAY_WAP, Payment::ALIPAY_WEB])) {
                if (empty($payReturn)) {
                    throw new Exception('支付回跳地址不能为空！');
                } else {
                    $this->config['return_url'] = $payReturn;
                }
            }
            if ($this->cfgType === Payment::WECHAT_APP) {
                $payment = App::instance($this->config);
            } elseif ($this->cfgType === Payment::ALIPAY_WAP) {
                $payment = Wap::instance($this->config);
            } elseif ($this->cfgType === Payment::ALIPAY_WEB) {
                $payment = Web::instance($this->config);
            } else {
                throw new Exception("支付类型[{$this->cfgType}]暂时不支持！");
            }
            $data = ['out_trade_no' => $orderNo, 'total_amount' => $payAmount, 'subject' => $payTitle];
            if (!empty($payRemark)) $data['body'] = $payRemark;
            $result = $payment->apply($data);
            // 创建支付记录
            $data = $this->createAction($orderNo, $payTitle, $payAmount);
            // 返回支付参数
            return ['result' => $result, 'data' => $data];
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 支付结果处理
     * @param array|null $data
     * @return \think\Response
     * @throws \WeChat\Exceptions\InvalidResponseException
     */
    public function notify(?array $data = null): Response
    {
        $notify = $data ?: App::instance($this->config)->notify();
        if (in_array($notify['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            if ($this->updateAction($notify['out_trade_no'], $notify['trade_no'], $notify['total_amount'])) {
                return response('success');
            } else {
                return response('error');
            }
        } else {
            return response('success');
        }
    }

    /**
     * 查询订单数据
     * @param string $orderno
     * @return array
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    public function query(string $orderno): array
    {
        return App::instance($this->config)->query($orderno);
    }
}