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
use think\admin\Exception;
use think\admin\extend\CodeExtend;
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
    public static function make(string $code, string $type, array $params): PaymentInterface
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
     * 创建支付行为
     * @param string $orderNo 商户订单单号
     * @param string $payTitle 商户订单标题
     * @param string $payAmount 需要支付金额
     * @param string $payImages 凭证支付图片
     * @return array
     * @throws \think\admin\Exception
     */
    protected function createAction(string $orderNo, string $payTitle, string $payAmount, string $payImages = ''): array
    {
        // 检查是否已经支付
        $map = ['order_no' => $orderNo, 'payment_status' => 1];
        if (($model = PluginPaymentRecord::mk()->where($map)->findOrEmpty())->isExists()) {
            throw new Exception("订单 {$orderNo} 已经完成支付！", 1);
        }
        // 写入订单支付行为
        $model->save([
            'unid'           => intval(sysvar('PluginPaymentUnid')),
            'usid'           => intval(sysvar('PluginPaymentUsid')),
            'order_no'       => $orderNo,
            'order_name'     => $payTitle,
            'order_amount'   => $payAmount,
            'payment_code'   => $this->cfgCode,
            'payment_type'   => $this->cfgType,
            'payment_images' => $payImages
        ]);
        return $model->toArray();
    }

    /**
     * 更新创建支付行为
     * @param string $orderno 商户订单单号
     * @param string $payTrade 平台交易单号
     * @param string $payAmount 实际到账金额
     * @param string $payRemark 平台支付备注
     * @return boolean|array
     */
    protected function updateAction(string $orderno, string $payTrade, string $payAmount, string $payRemark = '在线支付')
    {
        // 更新支付记录
        $map = ['order_no' => $orderno, 'payment_code' => $this->cfgCode, 'payment_type' => $this->cfgType];
        if (($model = PluginPaymentRecord::mk()->where($map)->findOrEmpty())->isEmpty()) return false;

        // 更新支付行为
        $model->save([
            'order_no'       => $orderno,
            'payment_code'   => $this->cfgCode,
            'payment_type'   => $this->cfgType,
            'payment_time'   => date('Y-m-d H:i:s'),
            'payment_trade'  => $payTrade,
            'payment_status' => 1,
            'payment_amount' => $payAmount,
            'payment_remark' => $payRemark,
        ]);

        // 触发支付成功事件
        $this->app->event->trigger('PluginPaymentSuccess', $model->refresh()->toArray());

        // 更新记录状态
        return $model->toArray();
    }

    /**
     * 获取账号编号
     * @param AccountInterface $account
     * @return integer
     * @throws \think\admin\Exception
     */
    protected function withUserUnid(AccountInterface $account): int
    {
        sysvar('PluginPaymentUsid', $this->withUserField($account, 'id'));
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
     * @param array $extra
     * @return string
     */
    protected function withNotifyUrl(string $order, string $scene = 'order', array $extra = []): string
    {
        $data = ['scen' => $scene, 'order' => $order, 'code' => $this->cfgCode];
        $vars = CodeExtend::enSafe64(json_encode($extra + $data, 64 | 256));
        return sysuri('@plugin-payment-notify', [], false, true) . "/{$vars}";
    }
}