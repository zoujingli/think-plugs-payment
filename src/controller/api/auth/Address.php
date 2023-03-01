<?php

// +----------------------------------------------------------------------
// | Account Plugin for ThinkAdmin
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

namespace plugin\payment\controller\api\auth;

use plugin\account\controller\api\Auth;
use plugin\payment\model\PluginPaymentAddress;

/**
 * 用户收货地址管理
 * Class Address
 * @package plugin\payment\controller\api\auth
 */
class Address extends Auth
{
    /**
     * 修改收货地址
     */
    public function set()
    {
        $data = $this->_vali([
            'id.default'          => 0,
            'unid.value'          => $this->unid,
            'type.default'        => 0,
            'idcode.default'      => '', // 身份证号码
            'idimg1.default'      => '', // 身份证正面
            'idimg2.default'      => '', // 身份证反面
            'type.in:0,1'         => '状态不在范围！',
            'name.require'        => '姓名不能为空！',
            'phone.mobile'        => '手机格式错误！',
            'phone.require'       => '手机不能为空！',
            'region_prov.require' => '省份不能为空！',
            'region_city.require' => '城市不能为空！',
            'region_area.require' => '区域不能为空！',
            'region_addr.require' => '地址不能为空！',
        ]);

        // 设置默认值
        $addr = $this->withDefault($data['id'], $data['type'], true);

        // 保存收货地址
        if ($addr->save($data) && $addr->isExists()) {
            $this->success('保存成功！', $addr->refresh()->toArray());
        } else {
            $this->error('保存失败！');
        }
    }

    /**
     * 获取收货地址
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get()
    {
        $query = $this->_query($this->withModel());
        $query->equal('id')->order('type desc,id desc');
        $this->success('获取地址数据！', $query->page(false, false));
    }

    /**
     * 修改地址状态
     * @return void
     */
    public function state()
    {
        $data = $this->_vali([
            'id.require'   => '编号不能为空！',
            'type.in:0,1'  => '状态不在范围！',
            'type.require' => '状态不能为空！',
        ]);

        // 检查是否存在
        $addr = $this->withDefault($data['id'], $data['type']);
        $addr->isEmpty() && $this->error('地址不存在！');

        // 返回成功消息
        $this->success('设置默认成功！', $addr->refresh()->toArray());
    }

    /**
     * 删除收货地址
     */
    public function remove()
    {
        $map = $this->_vali(['id.require' => '地址ID不能为空！']);
        $addr = $this->withModel($map)->findOrEmpty();
        if ($addr->isEmpty()) {
            $this->error('地址不存在！');
        } elseif ($addr->save(['deleted' => 1]) !== false) {
            $this->success('删除地址成功！');
        } else {
            $this->error('删除地址失败！');
        }
    }

    /**
     * 创建数据模型
     * @param mixed $map 地址查询条件
     * @return \plugin\payment\model\PluginPaymentAddress
     */
    private function withModel($map = []): PluginPaymentAddress
    {
        $model = PluginPaymentAddress::mk()->withoutField('deleted');
        return $model->where($map)->where(['unid' => $this->unid, 'deleted' => 0]);
    }

    /**
     * 取消默认选项
     * @param integer $addid 地址编号
     * @param integer $isdef 是否默认
     * @param boolean $force 强制更新
     * @return \plugin\payment\model\PluginPaymentAddress
     */
    private function withDefault(int $addid = 0, int $isdef = 1, bool $force = false): PluginPaymentAddress
    {
        $addr = $this->withModel(['id' => $addid])->findOrEmpty();
        if (intval($addr['type']) !== $isdef && $addr->isExists()) {
            $addr->save(['type' => $isdef]);
        }
        if (($force || $addr->isExists()) && $isdef > 0) {
            $this->withModel([['id', '<>', $addid]])->update(['type' => 0]);
        }
        return $addr;
    }
}