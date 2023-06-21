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

namespace plugin\payment\model;

use plugin\account\model\PluginAccountUser;
use plugin\payment\service\Payment;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 用户支付退款模型
 * @class PluginPaymentRecord
 * @package plugin\payment\model
 */
class PluginPaymentRefund extends Abs
{
    /**
     * 关联用户数据
     * @return \think\model\relation\HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(PluginAccountUser::class, 'id', 'unid');
    }

    /**
     * 关联订单所有子支付订单
     * @return \think\model\relation\HasMany
     */
    public function orders(): HasMany
    {
        return $this->hasMany(PluginPaymentRecord::class, 'order_no', 'order_no');
    }

    /**
     * 关联子支付订单
     * @return \think\model\relation\HasOne
     */
    public function record(): HasOne
    {
        return $this->hasOne(PluginPaymentRecord::class, 'code', 'record_code');
    }

    /**
     * @param $value
     * @return array
     */
    public function getUserAttr($value): array
    {
        return !is_array($value) ? [] : $value;
    }

    /**
     * 格式化输出时间
     * @param mixed $value
     * @return string
     */
    public function getRefundTimeAttr($value): string
    {
        return format_datetime($value);
    }

    /**
     * 数据输出处理
     * @return array
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['channel_type_name'] = Payment::typeName($data['channel_type']);
        return $data;
    }
}