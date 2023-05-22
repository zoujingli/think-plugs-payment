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

namespace plugin\payment\controller;

use plugin\account\model\PluginAccountUser;
use plugin\payment\model\PluginPaymentRecord;
use plugin\payment\service\Payment;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;

/**
 * 支付行为管理
 * @class Record
 * @package plugin\payment\controller
 */
class Record extends Controller
{
    /**
     * 支付行为管理
     * @auth true
     * @menu true
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        PluginPaymentRecord::mQuery()->layTable(function () {
            $this->title = '支付行为管理';
        }, function (QueryHelper $query) {
            $db = PluginAccountUser::mQuery()->like('email|nickname|username|phone#userinfo')->db();
            if ($db->getOptions('where')) $query->whereRaw("unid in {$db->field('id')->buildSql()}");
            $query->with(['user'])->like('order_no|order_name#orderinfo')->dateBetween('create_time');
        });
    }

    /**
     * 单据凭证支付审核
     * @auth true
     * @todo 需要做单凭支付审核
     */
    public function audit()
    {
        if ($this->request->isGet()) {
            PluginPaymentRecord::mForm('audit');
        } else {
            $data = $this->_vali([
                'id.require'     => '支付号不能为空！',
                'status.in:0,1'  => '审核状态数值异常！',
                'status.require' => '审核状态不能为空！',
                'remark.default' => '',
            ]);
            $action = PluginPaymentRecord::mk()->findOrEmpty($data['id']);
            if ($action->isEmpty()) $this->error('支付记录不存在！');
            if ($action->getAttr('channel_type') !== Payment::VOUCHER) {
                $this->error('不需要审核操作！');
            }
            if ($action->getAttr('payemnt_status') === 1) {
                $this->success('该凭证审已审核通过！');
            }
            $data['payment_time'] = date('Y-m-d H:i:s');
            $data['payment_trade'] = CodeExtend::uniqidDate(16, 'T');
            if (empty($data['status'])) {
                $data['payment_status'] = 2;
                $data['payment_remark'] = $data['remark'] ?: '后台审核凭证被驳回';
            } else {
                $data['payment_status'] = 1;
                $data['payment_remark'] = $data['remark'] ?: '后台支付凭证已通过';
            }
            if ($action->save($data)) {
                if (empty($data['status'])) {
                    $this->app->event->trigger('PluginPaymentCancel', $action->refresh()->toArray());
                    $this->success('凭证驳回并取消！');
                } else {
                    $this->app->event->trigger('PluginPaymentSuccess', $action->refresh()->toArray());
                    $this->success('订凭证审核通过！');
                }
            } else {
                $this->error('凭证审核失败！');
            }
        }
    }
}
