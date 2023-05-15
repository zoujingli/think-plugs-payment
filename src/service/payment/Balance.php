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
use plugin\payment\service\Balance as BalanceService;
use plugin\payment\service\contract\PaymentInterface;
use plugin\payment\service\contract\PaymentUsageTrait;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\Response;

/**
 * 账户余额支付方式
 * @class Balance
 * @package plugin\payment\service\payment
 */
class Balance implements PaymentInterface
{
    use PaymentUsageTrait;

    /**
     * 初始化支付通道
     * @return $this
     */
    public function init(): PaymentInterface
    {
        return $this;
    }

    /**
     * 订单信息查询
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
     */
    public function notify(?array $data = []): Response
    {
        return response('SUCCESS');
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
     * @throws \think\admin\Exception
     */
    public function create(AccountInterface $account, string $orderNo, string $payAmount, string $payTitle, string $payRemark, string $payReturn = '', string $payImages = ''): array
    {
        try {
            $unid = $this->withUserUnid($account);
            $this->app->db->transaction(function () use ($unid, $orderNo, $payTitle, $payAmount, $payRemark) {
                // 检查能否支付
                $data = BalanceService::recount($unid);
                if ($payAmount > $data['balance_usable']) throw new Exception('可抵扣的余额不足');
                // 创建支付行为
                $this->createAction($orderNo, $payTitle, $payAmount);
                // 扣除余额金额
                BalanceService::create($unid, $orderNo, $payTitle, -floatval($payAmount), $payRemark);
                // 更新支付行为
                $this->updateAction($orderNo, CodeExtend::uniqidDate(20), $payAmount, '账户余额支付');
            });
            // 刷新用户余额
            BalanceService::recount($unid);
            return ['code' => 1, 'info' => '余额支付完成'];
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }
}