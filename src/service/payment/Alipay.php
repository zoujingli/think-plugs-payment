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
use plugin\payment\service\contract\PaymentAbstract;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\Payment;
use think\admin\Exception;
use WeChat\Exceptions\InvalidResponseException;
use WeChat\Exceptions\LocalCacheException;

/**
 * 支付宝支付通道
 * Class Alipay
 * @package plugin\payment\service\payment
 */
class Alipay extends PaymentAbstract
{
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
     * 创建订单支付参数
     * @param AccountInterface $account 用户账号实例
     * @param string $orderno 交易订单单号
     * @param string $payAmount 交易订单金额（元）
     * @param string $payTitle 交易订单名称
     * @param string $payRemark 订单订单描述
     * @param string $payReturn 完成回跳地址
     * @param string $payImages 支付凭证图片
     * @return array
     * @throws Exception
     */
    public function create(AccountInterface $account, string $orderno, string $payAmount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        try {
            $this->config['notify_url'] = sysuri('api.notify/alipay', [], false, true) . "/scene/order/param/{$this->cfgCode}";
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
            $data = ['out_trade_no' => $orderno, 'total_amount' => $payAmount, 'subject' => $payTitle];
            if (!empty($payRemark)) $data['body'] = $payRemark;
            $result = $payment->apply($data);
            // 创建支付记录
            $this->createAction($orderno, $payTitle, $payAmount);
            // 返回支付参数
            return ['result' => $result];
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 支付结果处理
     * @return string
     * @throws InvalidResponseException
     */
    public function notify(): string
    {
        $notify = App::instance($this->config)->notify();
        if (in_array($notify['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            if ($this->updateAction($notify['out_trade_no'], $notify['trade_no'], $notify['total_amount'])) {
                return 'success';
            } else {
                return 'error';
            }
        } else {
            return 'success';
        }
    }

    /**
     * 查询订单数据
     * @param string $orderno
     * @return array
     * @throws InvalidResponseException
     * @throws LocalCacheException
     */
    public function query(string $orderno): array
    {
        return App::instance($this->config)->query($orderno);
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
}