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
use plugin\payment\model\PluginPaymentRecord;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentUsageTrait;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\Response;

/**
 * 单据凭证支付方式
 * Class Voucher
 * @package plugin\payment\service\payment
 */
class Voucher implements PaymentInterface
{
    use PaymentUsageTrait;

    /**
     * 初始化支付方式
     * @return PaymentInterface
     */
    public function init(): PaymentInterface
    {
        return $this;
    }

    /**
     * 订单数据查询
     * @param string $orderno
     * @return array
     */
    public function query(string $orderno): array
    {
        return [];
    }

    /**
     * 支付通知处理
     * @param array|null $data
     * @return \think\Response
     * @throws \think\admin\Exception
     */
    public function notify(?array $data = null): Response
    {
        $map = ['order_no' => $data['order_no'], 'payment_code' => $this->cfgCode, 'payment_type' => $this->cfgType];
        if (($model = PluginPaymentRecord::mk()->where($map)->findOrEmpty())->isEmpty()) {
            throw new Exception("支付行为记录不存在！");
        }
        $this->updateAction($data['order_no'], CodeExtend::uniqidDate(20), $model->getAttr('order_amount'));
        return response('success');
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
        if (empty($payImages)) throw new Exception('支付凭证不能为空');
        $data = $this->createAction($orderNo, $payTitle, $payAmount, $payImages);
        return ['code' => 1, 'info' => '支付凭证上传成功！', 'data' => $data];
    }
}