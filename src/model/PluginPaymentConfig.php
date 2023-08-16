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

/**
 * 用户支付参数模型
 * @class PluginPaymentConfig
 * @package plugin\payment\model
 */
class PluginPaymentConfig extends Abs
{
    protected $oplogName = '商城支付配置';
    protected $oplogType = '商城支付配置';

    /**
     * 格式化数据格式
     * @param mixed $value
     * @return string
     */
    public function setContentAttr($value): string
    {
        return is_array($value) ? json_encode($value, 64 | 256) : (string)$value;
    }

    /**
     * 格式化数据格式
     * @param mixed $value
     * @return array
     */
    public function getContentAttr($value): array
    {
        return is_string($value) ? json_decode($value, true) : [];
    }
}