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
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-account
// | github 代码仓库：https://github.com/zoujingli/think-plugs-account
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace plugin\payment\queue;

use plugin\account\model\PluginAccountUser;
use think\admin\Queue;

/**
 * 刷新用户余额
 * @class Balance
 * @package think\admin\Queue
 */
class Balance extends Queue
{
    /**
     * @param array $data
     * @return void
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DbException
     */
    public function execute(array $data = [])
    {
        [$total, $count, $error] = [PluginAccountUser::mk()->count(), 0, 0];
        foreach (PluginAccountUser::mk()->field('id')->cursor() as $user) try {
            $nick = $user['username'] ?: ($user['nickname'] ?: $user['email']);
            $this->queue->message($total, ++$count, "开始刷新用户 [{$user['id']} {$nick}] 余额");
            \plugin\payment\service\Balance::recount(intval($user['id']));
            $this->queue->message($total, $count, "刷新用户 [{$user['id']} {$nick}] 余额", 1);
        } catch (\Exception $exception) {
            $error++;
            $this->queue->message($total, $count, "刷新用户 [{$user['id']} {$nick}] 余额失败, {$exception->getMessage()}", 1);
        }
        $this->setQueueSuccess("此次共处理 {$total} 个刷新操作, 其中有 {$error} 个刷新失败。");
    }
}