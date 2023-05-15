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

use plugin\account\service\contract\AccountInterface;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentUsageTrait;
use plugin\payment\service\Payment;
use think\admin\Exception;
use think\admin\extend\HttpExtend;
use think\Response;

/**
 * 汇聚支付方式
 * Class Joinpay
 * @package plugin\payment\service\payment
 */
class Joinpay implements PaymentInterface
{
    use PaymentUsageTrait;

    const tradeTypes = [
        Payment::JOINPAY_GZH => 'WEIXIN_GZH',
        Payment::JOINPAY_XCX => 'WEIXIN_XCX'
    ];

    /**
     * 初始化支付方式
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
     * @param AccountInterface $account 用户OPENID
     * @param string $orderNo 交易订单单号
     * @param string $payAmount 交易订单金额（元）
     * @param string $payTitle 交易订单名称
     * @param string $payRemark 订单订单描述
     * @param string $payReturn 完成回跳地址
     * @param string $payImages 支付凭证图片
     * @return array
     * @throws \think\admin\Exception
     */
    public function create(AccountInterface $account, string $orderNo, string $payAmount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        $this->withUserUnid($account);
        $data = [
            'p0_Version'         => '1.0',
            'p1_MerchantNo'      => $this->config['mchid'],
            'p2_OrderNo'         => $orderNo,
            'p3_Amount'          => $payAmount,
            'p4_Cur'             => '1',
            'p5_ProductName'     => $payTitle,
            'p6_ProductDesc'     => $payRemark,
            'p9_NotifyUrl'       => $this->withNotifyUrl($orderNo),
            'q1_FrpCode'         => self::tradeTypes[$this->cfgType] ?? '',
            'q5_OpenId'          => $this->withUserField($account, 'openid'),
            'q7_AppId'           => $this->config['appid'],
            'qa_TradeMerchantNo' => $this->config['trade'],
        ];
        if (empty($data['q5_OpenId'])) unset($data['q5_OpenId']);
        $result = $this->_doReuest('uniPayApi.action', $data);
        if (isset($result['ra_Code']) && intval($result['ra_Code']) === 100) {
            // 创建支付记录
            $this->createAction($orderNo, $payTitle, $payAmount);
            // 返回支付参数
            return json_decode($result['rc_Result'], true);
        } else {
            throw new Exception($result['rb_CodeMsg'] ?? '获取预支付码失败！');
        }
    }

    /**
     * 查询订单数据
     * @param string $orderno
     * @return array
     */
    public function query(string $orderno): array
    {
        return $this->_doReuest('queryOrder.action', ['p1_MerchantNo' => $this->config['mchid'], 'p2_OrderNo' => $orderno]);
    }

    /**
     * 支付结果处理
     * @param array|null $data
     * @return \think\Response
     */
    public function notify(?array $data = null): Response
    {
        $notify = $data ?: $this->app->request->get();
        foreach ($notify as &$item) $item = urldecode($item);
        if (empty($notify['hmac']) || $notify['hmac'] !== $this->_doSign($notify)) {
            return response('error');
        }
        if (isset($notify['r6_Status']) && intval($notify['r6_Status']) === 100) {
            if ($this->updateAction($notify['r2_OrderNo'], $notify['r9_BankTrxNo'], $notify['r3_Amount'])) {
                return response('success');
            } else {
                return response('error');
            }
        } else {
            return response('success');
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