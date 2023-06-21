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
use plugin\payment\model\PluginPaymentRefund;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentUsageTrait;
use plugin\payment\service\Payment;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\Response;

/**
 * 支付宝支付通道
 * @class AliPayment
 * @package plugin\payment\service\payment
 */
class AliPayment implements PaymentInterface
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
            [$payCode] = [$this->withPayCode(), $this->withUserUnid($account)];
            $this->config['notify_url'] = $this->withNotifyUrl($payCode);
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
            $param = ['out_trade_no' => $payCode, 'total_amount' => $payAmount, 'subject' => $orderTitle];
            if (!empty($orderRemark)) $param['body'] = $orderRemark;
            // 创建支付记录
            $data = $this->createAction($orderNo, $orderTitle, $orderAmount, $payCode, $payAmount);
            // 返回支付参数
            return ['code' => 1, 'info' => '创建支付成功', 'data' => $data, 'param' => $payment->apply($param)];
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
        $pay = $this->checkRefund($pcode, $amount);
        $code = CodeExtend::uniqidNumber(16, 'R');
        $model = PluginPaymentRefund::mk()->whereRaw('1<>1')->findOrEmpty();
        // 写入退款记录
        $model->save(array_merge($pay->toArray(), [
            'code'          => $code,
            'record_code'   => $pay->getAttr('code'),
            'refund_amount' => $amount,
            'refund_status' => 1,
            'refund_time'   => date('Y-m-d H:i:s'),
            'used_payment'  => $amount
        ]));
        // 发起退款操作
        App::instance($this->config)->refund([
            'out_trade_no'   => $pcode,
            'out_request_no' => $code,
            'refund_amount'  => $amount,
        ]);
        // 刷新退款金额
        $refundAmount = PluginPaymentRefund::mk()->where(['record_code' => $pay->getAttr('code')])->sum('refund_amount');
        $pay->save(['refund_status' => 1, 'refund_amount' => $refundAmount]);
        return $model->toArray();
    }

    /**
     * 查询订单数据
     * @param string $pcode
     * @return array
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    public function query(string $pcode): array
    {
        return App::instance($this->config)->query($pcode);
    }
}