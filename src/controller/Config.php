<?php

namespace plugin\payment\controller;

use plugin\account\service\Account;
use plugin\payment\model\PluginPaymentConfig;
use plugin\payment\service\Payment;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;

/**
 * 支付通道管理
 * @class Config
 * @package plugin\payment\controller
 */
class Config extends Controller
{

    /**
     * 支付通道管理
     * @auth true
     * @menu true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index()
    {
        $this->type = $this->get['type'] ?? 'index';
        PluginPaymentConfig::mQuery()->layTable(function () {
            $this->title = '支付通道管理';
            $this->types = Payment::types(1);
        }, function (QueryHelper $query) {
            $query->withoutField('content');
            $query->where(['status' => intval($this->type === 'index'), 'deleted' => 0]);
            $query->like('name,code')->equal('status,type#ptype')->dateBetween('create_time');
        });
    }

    /**
     * 获取支付通道
     * @param array $data
     * @return void
     */
    protected function _page_filter(array &$data)
    {
        [$ptypes, $atypes] = [Payment::types(), Account::types(1)];
        foreach ($data as &$vo) {
            [$vo['ntype'], $vo['atype']] = [$ptypes[$vo['type']]['name'] ?? $vo['type'], []];
            if (isset($ptypes[$vo['type']])) foreach ($ptypes[$vo['type']]['account'] as $account) {
                if (isset($atypes[$account])) $vo['atype'][$account] = $atypes[$account]['name'];
            }
        }
    }

    /**
     * 添加支付通道
     * @auth true
     */
    public function add()
    {
        $this->title = '添加支付通道';
        PluginPaymentConfig::mForm('form');
    }

    /**
     * 编辑支付通道
     * @auth true
     */
    public function edit()
    {
        $this->title = '编辑支付通道';
        PluginPaymentConfig::mForm('form');
    }

    /**
     * 数据表单处理
     * @param array $data
     */
    protected function _form_filter(array &$data)
    {
        if (empty($data['code'])) {
            $data['code'] = CodeExtend::uniqidNumber(16, 'M');
        }
        if ($this->request->isGet()) {
            $data['content'] = $data['content'] ?? [];
            [$this->payments, $types] = [[], Account::types(1)];
            foreach (Payment::types(1) as $k => $v) {
                $allow = [];
                foreach ($v['account'] as $api) if (isset($types[$api])) {
                    $allow[$api] = $types[$api]['name'];
                }
                if (empty($allow)) continue;
                $this->payments[$k] = array_merge($v, ['allow' => join('、', $allow)]);
            }
        } else {
            if (empty($data['type'])) $this->error('请选择支付方式！');
            if (empty($data['cover'])) $this->error('请上传支付通道图标！');
            // 保存配置参数
            $data['content'] = $this->request->post();
            $fields = PluginPaymentConfig::mk()->getTableFields();
            foreach ($data['content'] as $k => $v) {
                if (in_array($k, $fields) || $v === '') unset($data['content'][$k]);
            }
        }
    }

    /**
     * 处理结果处理
     * @param boolean $state
     * @return void
     */
    protected function _form_result(bool &$state)
    {
        if ($state) {
            $this->success('参数保存成功！', 'javascript:history.back()');
        }
    }

    /**
     * 修改通道状态
     * @auth true
     */
    public function state()
    {
        PluginPaymentConfig::mSave($this->_vali([
            'status.in:0,1'  => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除支付通道
     * @auth true
     */
    public function remove()
    {
        PluginPaymentConfig::mDelete();
    }

    /**
     * 配置支付方式
     * @auth true
     * @throws \think\admin\Exception
     */
    public function types()
    {
        $this->types = Payment::types();
        $this->config = sysdata('plugin.payment.config');
        if ($this->request->isGet()) {
            $this->fetch();
        } else {
            sysdata('plugin.payment.config', $this->request->post([
                'types', 'integral'
            ]));
            $types = $this->request->post('types', []);
            foreach ($this->types as $k => $v) {
                Payment::set($k, intval(in_array($k, $types)));
            }
            if (Payment::save()) {
                $this->success('配置保存成功！');
            } else {
                $this->error('配置保存失败！');
            }
        }
    }
}