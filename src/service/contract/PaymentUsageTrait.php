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

namespace plugin\payment\service\contract;

use plugin\account\service\contract\AccountInterface;
use plugin\payment\model\PluginPaymentRecord;
use plugin\payment\model\PluginPaymentRefund;
use plugin\payment\service\Payment;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\Library;
use think\admin\service\AdminService;
use think\App;

/**
 * 支付方式公共操作
 * @class PaymentUsageTrait
 * @package plugin\payment\service\contract
 */
trait PaymentUsageTrait
{
    /**
     * 当前应用对象
     * @var \think\App
     */
    protected $app;

    /**
     * 支付调度参数
     * @var array
     */
    protected $config;

    /**
     * 支付方式编号
     * @var string
     */
    protected $cfgCode;

    /**
     * 支付方式类型
     * @var string
     */
    protected $cfgType;

    /**
     * 支付方式参数
     * @var array
     */
    protected $cfgParams;

    /**
     * 支付方式构造函数
     * @param \think\App $app
     * @param string $code
     * @param string $type
     * @param array $params
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    public function __construct(App $app, string $code, string $type, array $params)
    {
        $this->app = $app;
        $this->cfgCode = $code;
        $this->cfgType = $type;
        $this->cfgParams = $params;
        $this->init();
    }

    /**
     * 支付实例创建器
     * @param string $code
     * @param string $type
     * @param array $params
     * @return \plugin\payment\service\contract\PaymentInterface
     */
    public static function mk(string $code, string $type, array $params): PaymentInterface
    {
        /** @var \plugin\payment\service\contract\PaymentInterface */
        return app(static::class, ['code' => $code, 'type' => $type, 'params' => $params]);
    }

    /**
     * 初始化支付方式
     * @return PaymentInterface
     */
    abstract public function init(): PaymentInterface;

    /**
     * 检查订单支付金额
     * @param string $orderNo
     * @param mixed $payAmount
     * @param mixed $orderAmount
     * @return float
     * @throws \think\admin\Exception
     */
    protected function checkLeaveAmount($orderNo, $payAmount, $orderAmount): float
    {
        // 检查未审核的记录
        $map = ['order_no' => $orderNo, 'audit_status' => 1];
        $find = PluginPaymentRecord::mk()->where($map)->findOrEmpty();
        if ($find->isExists()) throw new Exception('凭证待审核！', 0);

        // 检查支付金额是否超出
        if (floatval($payAmount) + Payment::paidAmount($orderNo) > floatval($orderAmount)) {
            throw new Exception("支付超出订单金额！");
        }
        return floatval($payAmount);
    }

    /**
     * 创建支付行为
     * @param string $orderNo 订单单号
     * @param string $orderTitle 订单标题
     * @param string $orderAmount 订单总金额
     * @param string $payCode 此次支付单号
     * @param string $payAmount 此次支付金额
     * @param string $payImages 支付凭证图片
     * @param string $usedBalance 使用余额
     * @param string $usedIntegral 使用积分
     * @return array
     * @throws \think\admin\Exception
     */
    protected function createAction(string $orderNo, string $orderTitle, string $orderAmount, string $payCode, string $payAmount, string $payImages = '', string $usedBalance = '0.00', string $usedIntegral = '0.00'): array
    {
        // 检查是否已经支付
        $map = ['order_no' => $orderNo, 'payment_status' => 1];
        $total = PluginPaymentRecord::mk()->where($map)->sum('payment_amount');
        if ($total >= floatval($orderAmount) && $orderAmount > 0) {
            throw new Exception("已经完成支付！", 1);
        }
        if ($total + floatval($payAmount) > floatval($orderAmount)) {
            throw new Exception('支付大于金额！', 0);
        }
        $map['code'] = $payCode;
        if (($model = PluginPaymentRecord::mk()->where($map)->findOrEmpty())->isExists()) {
            throw new Exception("已经完成支付！", 1);
        }
        // 写入订单支付行为
        $model->save([
            'unid'           => intval(sysvar('PluginPaymentUnid')),
            'usid'           => intval(sysvar('PluginPaymentUsid')),
            'code'           => $payCode,
            'order_no'       => $orderNo,
            'order_name'     => $orderTitle,
            'order_amount'   => $orderAmount,
            'channel_code'   => $this->cfgCode,
            'channel_type'   => $this->cfgType,
            'payment_amount' => $this->cfgType === Payment::VOUCHER ? $payAmount : 0.00,
            'payment_images' => $payImages,
            'audit_status'   => $this->cfgType === Payment::VOUCHER ? 1 : 2,
            'audit_time'     => date("Y-m-d H:i:s"),
            'used_payment'   => $payAmount,
            'used_balance'   => $usedBalance,
            'used_integral'  => $usedIntegral,
        ]);

        // 触发支付审核事件
        $record = $model->refresh();
        if ($this->cfgType === Payment::VOUCHER) {
            $this->app->event->trigger('PluginPaymentAudit', $record);
        }
        return $record->toArray();
    }

    /**
     * 更新创建支付行为
     * @param string $payCode 商户订单单号
     * @param string $payTrade 平台交易单号
     * @param string $payAmount 实际到账金额
     * @param string $payRemark 平台支付备注
     * @return boolean|array
     */
    protected function updateAction(string $payCode, string $payTrade, string $payAmount, string $payRemark = '在线支付')
    {
        // 更新支付记录
        $map = ['code' => $payCode, 'channel_code' => $this->cfgCode, 'channel_type' => $this->cfgType];
        if (($model = PluginPaymentRecord::mk()->where($map)->findOrEmpty())->isEmpty()) return false;

        // 更新支付行为
        $model->save([
            'code'           => $payCode,
            'channel_code'   => $this->cfgCode,
            'channel_type'   => $this->cfgType,
            'payment_time'   => date('Y-m-d H:i:s'),
            'payment_trade'  => $payTrade,
            'payment_status' => 1,
            'payment_amount' => $payAmount,
            'payment_remark' => $payRemark,
        ]);

        // 触发支付成功事件
        $this->app->event->trigger('PluginPaymentSuccess', $model->refresh());

        // 更新记录状态
        return $model->toArray();
    }

    /**
     * 同步退款统计状态
     * @param string $pCode 支付单号
     * @param ?string $rCode 退款单号&引用
     * @param ?string $amount 退款金额 ( null 表示需要处理退款，仅同步数据 )
     * @param string $reason 退款原因
     * @return \plugin\payment\model\PluginPaymentRecord
     * @throws \think\admin\Exception
     */
    public static function syncRefund(string $pCode, ?string &$rCode = '', ?string $amount = null, string $reason = ''): PluginPaymentRecord
    {
        // 查询支付记录
        $record = PluginPaymentRecord::mk()->where(['code' => $pCode])->findOrEmpty();
        if ($record->isEmpty()) throw new Exception('支付单不存在！');
        if ($record->getAttr('payment_status') < 1) throw new Exception('支付未完成！');
        // 统计刷新退款金额
        $rWhere = ['record_code' => $pCode, 'refund_status' => 1];
        $rAmount = PluginPaymentRefund::mk()->where($rWhere)->sum('refund_amount');
        $record->save(['refund_amount' => $rAmount, 'refund_status' => intval($rAmount > 0)]);
        // 是否需要写入退款
        if (!is_numeric($amount)) return $record->refresh();
        // 生成退款记录
        $pType = $record->getAttr('channel_type');
        $extra = ['used_payment' => $amount, 'refund_status' => 0];
        if (in_array($pType, [Payment::EMPTY, Payment::BALANCE, Payment::INTEGRAL, Payment::VOUCHER])) {
            if ($pType === Payment::BALANCE) $extra['used_balance'] = $amount;
            elseif ($pType === Payment::INTEGRAL) {
                $extra['used_integral'] = intval(floatval($amount) / floatval($record->getAttr('payment_amount')) * $record->getAttr('used_integral'));
            }
            $extra['refund_trade'] = CodeExtend::uniqidNumber(16, 'RT');
            $extra['refund_account'] = $pType;
            $extra['refund_scode'] = 'SUCCESS';
            $extra['refund_status'] = 1;
            $extra['refund_time'] = date('Y-m-d H:i:s');
        }
        // 支付金额大于0，并需要创建退款记录
        if ($record->getAttr('payment_amount') > 0 && $rAmount + floatval($amount) <= $record->getAttr('payment_amount')) {
            PluginPaymentRefund::mk()->save(array_merge([
                'unid' => $record->getAttr('unid'), 'record_code' => $pCode,
                'usid' => $record->getAttr('usid'), 'refund_amount' => $amount,
                'code' => $rCode = Payment::withRefundCode(), 'refund_remark' => $reason,
            ], $extra));
            // 刷新退款金额
            $rAmount = PluginPaymentRefund::mk()->where($rWhere)->sum('refund_amount');
        }
        // 刷新退款金额
        $record->save([
            'refund_status' => 1,
            'refund_amount' => $rAmount,
            'audit_time'    => date('Y-m-d H:i:s'),
            'audit_user'    => AdminService::getUserId(),
            'audit_status'  => 0,
            'audit_remark'  => '已申请取消支付，' . ($reason ?: '后台取消！')
        ]);
        // 触发取消支付事件
        Library::$sapp->event->trigger('PluginPaymentCancel', $record->refresh());
        return $record;
    }

    /**
     * 获取账号编号
     * @param AccountInterface $account
     * @return integer
     * @throws \think\admin\Exception
     */
    protected function withUserUnid(AccountInterface $account): int
    {
        sysvar('PluginPaymentUsid', intval($this->withUserField($account, 'id')));
        return sysvar('PluginPaymentUnid', intval($this->withUserField($account, 'unid')));
    }

    /**
     * 获取账号指定字段
     * @param AccountInterface $account
     * @param string $field
     * @return mixed|string
     * @throws \think\admin\Exception
     */
    protected function withUserField(AccountInterface $account, string $field)
    {
        $auth = $account->get();
        if (isset($auth[$field])) return $auth[$field];
        throw new Exception("获取 {$field} 字段值失败！");
    }

    /**
     * 获取通知地址
     * @param string $order 订单单号
     * @param string $scene 支付场景
     * @param array $extra 扩展数据
     * @return string
     */
    protected function withNotifyUrl(string $order, string $scene = 'order', array $extra = []): string
    {
        $data = ['scen' => $scene, 'order' => $order, 'channel' => $this->cfgCode];
        $vars = CodeExtend::enSafe64(json_encode($extra + $data, 64 | 256));
        return sysuri('@plugin-payment-notify', [], false, true) . "/{$vars}";
    }
}