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

namespace plugin\payment\support\payment;

use plugin\payment\support\contract\PaymentAbstract;
use plugin\payment\support\contract\PaymentInterface;
use think\admin\Exception;
use think\admin\extend\HttpExtend;

/**
 * 汇聚支付通道
 * Class Joinpay
 * @package plugin\payment\support\payment
 */
class Joinpay extends PaymentAbstract
{

    /**
     * 初始化支付通道
     * @return PaymentInterface
     */
    public function init(): PaymentInterface
    {
        $this->config['appid'] = $this->cfgParams['joinpay_appid'];
        $this->config['trade'] = $this->cfgParams['joinpay_trade'];
        $this->config['mchid'] = $this->cfgParams['joinpay_mch_id'];
        $this->config['mchkey'] = $this->cfgParams['joinpay_mch_key'];
        return $this;
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
     * @throws Exception
     */
    public function create(string $openid, string $orderNo, string $amount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        try {
            $data = [
                'p0_Version'         => '1.0',
                'p1_MerchantNo'      => $this->config['mchid'],
                'p2_OrderNo'         => $orderNo,
                'p3_Amount'          => $amount,
                'p4_Cur'             => '1',
                'p5_ProductName'     => $payTitle,
                'p6_ProductDesc'     => $payRemark,
                'p9_NotifyUrl'       => sysuri("@data/api.notify/joinpay/scene/order/param/{$this->code}", [], false, true),
                'q1_FrpCode'         => $tradeType ?? '',
                'q5_OpenId'          => $openid,
                'q7_AppId'           => $this->config['appid'],
                'qa_TradeMerchantNo' => $this->config['trade'],
            ];
            if (empty($data['q5_OpenId'])) unset($data['q5_OpenId']);
            $result = $this->_doReuest('uniPayApi.action', $data);
            if (isset($result['ra_Code']) && intval($result['ra_Code']) === 100) {
                // 创建支付记录
                $this->createAction($orderNo, $payTitle, $amount);
                // 返回支付参数
                return json_decode($result['rc_Result'], true);
            } elseif (isset($result['rb_CodeMsg'])) {
                throw new Exception($result['rb_CodeMsg']);
            } else {
                throw new Exception('获取预支付码失败！');
            }
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 查询订单数据
     * @param string $orderNo
     * @return array
     */
    public function query(string $orderNo): array
    {
        return $this->_doReuest('queryOrder.action', ['p1_MerchantNo' => $this->config['mchid'], 'p2_OrderNo' => $orderNo]);
    }

    /**
     * 支付结果处理
     * @return string
     */
    public function notify(): string
    {
        $notify = $this->app->request->get();
        foreach ($notify as &$item) $item = urldecode($item);
        if (empty($notify['hmac']) || $notify['hmac'] !== $this->_doSign($notify)) {
            return 'error';
        }
        if (isset($notify['r6_Status']) && intval($notify['r6_Status']) === 100) {
            if ($this->updateAction($notify['r2_OrderNo'], $notify['r9_BankTrxNo'], $notify['r3_Amount'])) {
                return 'success';
            } else {
                return 'error';
            }
        } else {
            return 'success';
        }
    }

    /**
     * 执行数据请求
     * @param string $uri
     * @param array $data
     * @return array
     */
    private function _doReuest(string $uri, array $data = []): array
    {
        $main = "https://www.joinpay.com/trade";
        $data['hmac'] = $this->_doSign($data);
        return json_decode(HttpExtend::post("{$main}/{$uri}", $data), true);
    }

    /**
     * 请求数据签名
     * @param array $data
     * @return string
     */
    private function _doSign(array $data): string
    {
        ksort($data);
        unset($data['hmac']);
        return md5(join('', $data) . $this->config['mchkey']);
    }

}