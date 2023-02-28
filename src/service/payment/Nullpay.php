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
use plugin\payment\model\ShopOrder;
use plugin\payment\service\contract\PaymentAbstract;
use plugin\payment\service\contract\PaymentInterface;
use think\admin\Exception;
use think\admin\extend\CodeExtend;

/**
 * 空支付支付通道
 * Class Nullpay
 * @package plugin\payment\service\payment
 */
class Nullpay extends PaymentAbstract
{

    /**
     * 初始化支付通道
     * @return PaymentInterface
     */
    public function init(): PaymentInterface
    {
        return $this;
    }

    /**
     * 订单主动查询
     * @param string $orderno
     * @return array
     */
    public function query(string $orderno): array
    {
        return [];
    }

    /**
     * 支付通知处理
     * @return string
     */
    public function notify(): string
    {
        return '';
    }

    /**
     * 创建订单支付参数
     * @param AccountInterface $account 用户OPENID
     * @param string $orderno 交易订单单号
     * @param string $payAmount 交易订单金额（元）
     * @param string $payTitle 交易订单名称
     * @param string $payRemark 订单订单描述
     * @param string $payReturn 完成回跳地址
     * @param string $payImages 支付凭证图片
     * @return array
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function create(AccountInterface $account, string $orderno, string $payAmount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        $order = ShopOrder::mk()->where(['order_no' => $orderno])->find();
        if (empty($order)) throw new Exception("订单不存在");
        if ($order['status'] !== 2) throw new Exception("不可发起支付");
        // 创建支付行为
        $this->createAction($orderno, $payTitle, $payAmount);
        // 更新支付行为
        $this->updateAction($orderno, CodeExtend::uniqidDate(20), $payAmount, '无需支付');
        return ['code' => 1, 'info' => '订单无需支付'];
    }
}