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

namespace plugin\payment\service;

use plugin\account\model\PluginAccountUser;
use plugin\payment\model\PluginPaymentBalance;
use think\admin\Exception;

/**
 * 用户余额调度器
 * Class Balance
 * @package plugin\payment\service
 */
abstract class Balance
{
    /**
     * 创建余额变更操作
     * @param integer $unid 账号编号
     * @param string $code 交易标识
     * @param string $name 交易标题
     * @param float $amount 变更金额
     * @param string $remark 变更描述
     * @return PluginPaymentBalance
     * @throws \think\admin\Exception
     */
    public static function create(int $unid, string $code, string $name, float $amount, string $remark = ''): PluginPaymentBalance
    {
        $user = PluginAccountUser::mk()->findOrEmpty($unid);
        if ($user->isEmpty()) throw new Exception('账号不存在！');

        // 扣减余额检查
        $map = ['unid' => $unid, 'cancel' => 0, 'deleted' => 0];
        $usable = PluginPaymentBalance::mk()->where($map)->sum('amount');
        if ($amount < 0 && abs($amount) > $usable) throw new Exception('扣减余额不足！');

        // 检查编号是否重复
        $map = ['unid' => $unid, 'code' => $code, 'deleted' => 0];
        $model = PluginPaymentBalance::mk()->where($map)->findOrEmpty();

        // 更新或写入余额变更
        $model->save([
            'unid'        => $unid,
            'code'        => $code,
            'name'        => $name,
            'amount'      => $amount,
            'remark'      => $remark,
            'status'      => 1,
            'unlock'      => 0,
            'unlock_time' => date('Y-m-d H:i:s'),
            //'create_by'   => AdminService::getUserId()
        ]);
        if ($model->isExists()) {
            self::recount($unid);
            return $model->refresh();
        } else {
            throw new Exception('余额变更失败！');
        }
    }

    /**
     * 解锁余额变更操作
     * @param string $code 交易订单
     * @param integer $unlock 锁定状态
     * @return PluginPaymentBalance
     * @throws \think\admin\Exception
     */
    public static function unlock(string $code, int $unlock = 1): PluginPaymentBalance
    {
        return self::set($code, ['unlock' => $unlock, 'unlock_time' => date('Y-m-d H:i:s')]);
    }

    /**
     * 作废余额变更操作
     * @param string $code 交易订单
     * @param integer $cancel 取消状态
     * @return PluginPaymentBalance
     * @throws \think\admin\Exception
     */
    public static function cancel(string $code, int $cancel = 1): PluginPaymentBalance
    {
        return self::set($code, ['cancel' => $cancel, 'cancel_time' => date('Y-m-d H:i:s')]);
    }

    /**
     * 删除余额记录
     * @param string $code
     * @return PluginPaymentBalance
     * @throws \think\admin\Exception
     */
    public static function remove(string $code): PluginPaymentBalance
    {
        return self::set($code, ['deleted' => 1, 'deleted_time' => date('Y-m-d H:i:s')]);
    }

    /**
     * 刷新用户余额
     * @param integer $unid
     * @return array
     * @throws \think\admin\Exception
     */
    public static function recount(int $unid): array
    {
        $user = PluginAccountUser::mk()->findOrEmpty($unid);
        if ($user->isEmpty()) throw new Exception('账号不存在！');

        // 统计用户余额数据
        $map = ['unid' => $unid, 'cancel' => 0, 'deleted' => 0];
        $lock = PluginPaymentBalance::mk()->where($map)->where('unlock', '=', '0')->sum('amount');
        $used = PluginPaymentBalance::mk()->where($map)->where('amount', '<', '0')->sum('amount');
        $total = PluginPaymentBalance::mk()->where($map)->where('amount', '>', '0')->sum('amount');

        // 更新用户余额统计
        $data = [
            'balance_lock'  => $lock, 'balance_used' => abs($used),
            'balance_total' => $total, 'balance_usable' => $total - abs($used),
        ];
        $user->setAttr('extra', array_merge($user->getAttr('extra'), $data));
        $user->save();
        return $data;
    }

    /**
     * 获取余额模型
     * @param string $code
     * @return PluginPaymentBalance
     * @throws \think\admin\Exception
     */
    public static function get(string $code): PluginPaymentBalance
    {
        $map = ['code' => $code, 'deleted' => 0];
        $model = PluginPaymentBalance::mk()->where($map)->findOrEmpty();
        if ($model->isEmpty()) throw new Exception('无效的操作编号！');
        return $model;
    }

    /**
     * 更新余额记录
     * @param string $code
     * @param array $data
     * @return PluginPaymentBalance
     * @throws \think\admin\Exception
     */
    public static function set(string $code, array $data): PluginPaymentBalance
    {
        ($model = self::get($code))->save($data);
        self::recount($model->getAttr('unid'));
        return $model->refresh();
    }
}