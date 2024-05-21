<?php

// +----------------------------------------------------------------------
// | Payment Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2024 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 会员免费 ( https://thinkadmin.top/vip-introduce )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-payment
// | github 代码仓库：https://github.com/zoujingli/think-plugs-payment
// +----------------------------------------------------------------------

use think\migration\Migrator;

@set_time_limit(0);
@ini_set('memory_limit', -1);

class InstallPayment20240403 extends Migrator
{

    /**
     * 创建数据库
     */
    public function change()
    {
        // 当前数据表
        $table = 'plugin_payment_record';

        // 检查与更新数据表
        $this->table($table)->hasColumn('payment_coupon') || $this->table($table)
            ->addColumn('payment_coupon', 'decimal', ['after' => 'payment_amount', 'precision' => 20, 'scale' => 2, 'default' => '0.00', 'null' => true, 'comment' => '平台优惠券金额'])
            ->addColumn('payment_notify', 'text', ['after' => 'payment_remark', 'default' => NULL, 'null' => true, 'comment' => '支付通知内容'])
            ->update();
    }
}