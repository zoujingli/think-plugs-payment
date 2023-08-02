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

/**
 * 支付响应对象
 * @class PaymentResponse
 * @package plugin\payment\service\contract
 */
class PaymentResponse extends \stdClass
{
    public $data;
    public $status;
    public $params;
    public $message;

    public function __construct(bool $status = true, string $message = "创建支付成功", array $data = [], array $params = [])
    {
        $this->data = $data;
        $this->status = $status;
        $this->params = $params;
        $this->message = $message;
    }

    /**
     * 输出数组数据
     * @return array
     */
    public function toArray(): array
    {
        return ['record' => $this->data, 'params' => $this->params];
    }

    /**
     * 创建支付响应对象
     * @param boolean $status
     * @param string $message
     * @param array $data
     * @param array $params
     * @return \plugin\payment\service\contract\PaymentResponse
     */
    public static function mk(bool $status = true, string $message = "创建支付成功", array $data = [], array $params = []): PaymentResponse
    {
        return new static($status, $message, $data, $params);
    }
}