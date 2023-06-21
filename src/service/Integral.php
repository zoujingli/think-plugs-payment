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
use plugin\payment\model\PluginPaymentIntegral;
use think\admin\Exception;

/**
 * 用户积分调度器
 * @class Integral
 * @package plugin\payment\service
 */
abstract class Integral
{
    /**
     * 创建积分变更操作
     * @param integer $unid 账号编号
     * @param string $code 交易标识
     * @param string $name 交易标题
     * @param float $amount 变更金额
     * @param string $remark 变更描述
     * @param boolean $unlock 解锁状态
     * @return PluginPaymentIntegral
     * @throws \think\admin\Exception
     */
    public static function create(int $unid, string $code, string $name, float $amount, string $remark = '', bool $unlock = false): PluginPaymentIntegral
    {
        $user = PluginAccountUser::mk()->findOrEmpty($unid);
        if ($user->isEmpty()) throw new Exception('账号不存在！');

        // 扣减积分检查
        $map = ['unid' => $unid, 'cancel' => 0, 'deleted' => 0];
        $usable = PluginPaymentIntegral::mk()->where($map)->sum('amount');
        if ($amount < 0 && abs($amount) > $usable) throw new Exception('扣减积分不足！');

        // 检查编号是否重复
        $map = ['unid' => $unid, 'code' => $code, 'deleted' => 0];
        $model = PluginPaymentIntegral::mk()->where($map)->findOrEmpty();

        // 更新或写入积分变更
        $model->save([
            'unid'        => $unid,
            'code'        => $code,
            'name'        => $name,
            'amount'      => $amount,
            'remark'      => $remark,
            'status'      => 1,
            'unlock'      => $unlock ? 1 : 0,
            'unlock_time' => date('Y-m-d H:i:s')
        ]);
        if ($model->isExists()) {
            self::recount($unid);
            return $model->refresh();
        } else {
            throw new Exception('积分变更失败！');
        }
    }

    /**
     * 解锁积分变更操作
     * @param string $code 交易订单
     * @param integer $unlock 锁定状态
     * @return PluginPaymentIntegral
     * @throws \think\admin\Exception
     */
    public static function unlock(string $code, int $unlock = 1): PluginPaymentIntegral
    {
        return self::set($code, ['unlock' => $unlock, 'unlock_time' => date('Y-m-d H:i:s')]);
    }

    /**
     * 作废积分变更操作
     * @param string $code 交易订单
     * @param integer $cancel 取消状态
     * @return PluginPaymentIntegral
     * @throws \think\admin\Exception
     */
    public static function cancel(string $code, int $cancel = 1): PluginPaymentIntegral
    {
        return self::set($code, ['cancel' => $cancel, 'cancel_time' => date('Y-m-d H:i:s')]);
    }

    /**
     * 删除积分记录
     * @param string $code
     * @return PluginPaymentIntegral
     * @throws \think\admin\Exception
     */
    public static function remove(string $code): PluginPaymentIntegral
    {
        return self::set($code, ['deleted' => 1, 'deleted_time' => date('Y-m-d H:i:s')]);
    }

    /**
     * 刷新用户积分
     * @param integer $unid
     * @return array [lock,used,total,usable]
     * @throws \think\admin\Exception
     */
    public static function recount(int $unid): array
    {
        $user = PluginAccountUser::mk()->findOrEmpty($unid);
        if ($user->isEmpty()) throw new Exception('账号不存在！');

        // 统计用户积分数据
        $map = ['unid' => $unid, 'cancel' => 0, 'deleted' => 0];
        $lock = PluginPaymentIntegral::mk()->where($map)->where('unlock', '=', '0')->sum('amount');
        $used = PluginPaymentIntegral::mk()->where($map)->where('amount', '<', '0')->sum('amount');
        $total = PluginPaymentIntegral::mk()->where($map)->where('amount', '>', '0')->sum('amount');

        // 更新积分统计
        $data = [
            'integral_lock'  => $lock, 'integral_used' => abs($used),
            'integral_total' => $total, 'integral_usable' => $total - abs($used),
        ];
        $user->save(['extra' => array_merge($user->getAttr('extra'), $data)]);
        return ['lock' => $lock, 'used' => abs($used), 'total' => $total, 'usable' => $data['integral_usable']];
    }

    /**
     * 获取积分模型
     * @param string $code
     * @return PluginPaymentIntegral
     * @throws \think\admin\Exception
     */
    public static function get(string $code): PluginPaymentIntegral
    {
        $map = ['code' => $code, 'deleted' => 0];
        $model = PluginPaymentIntegral::mk()->where($map)->findOrEmpty();
        if ($model->isEmpty()) throw new Exception('无效的操作编号！');
        return $model;
    }

    /**
     * 更新积分记录
     * @param string $code
     * @param array $data
     * @return PluginPaymentIntegral
     * @throws \think\admin\Exception
     */
    public static function set(string $code, array $data): PluginPaymentIntegral
    {
        ($model = self::get($code))->save($data);
        self::recount($model->getAttr('unid'));
        return $model->refresh();
    }
}