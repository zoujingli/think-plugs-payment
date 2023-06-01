# ThinkPlugsPayment for ThinkAdmin

[![Latest Stable Version](https://poser.pugx.org/zoujingli/think-plugs-payment/v/stable)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![Latest Unstable Version](https://poser.pugx.org/zoujingli/think-plugs-payment/v/unstable)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![Total Downloads](https://poser.pugx.org/zoujingli/think-plugs-payment/downloads)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![Monthly Downloads](https://poser.pugx.org/zoujingli/think-plugs-payment/d/monthly)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![Daily Downloads](https://poser.pugx.org/zoujingli/think-plugs-payment/d/daily)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![PHP Version](https://doc.thinkadmin.top/static/icon/php-7.1.svg)](https://thinkadmin.top)
[![License](https://doc.thinkadmin.top/static/icon/license-vip.svg)](https://thinkadmin.top/vip-introduce)

**注意：** 该插件测试版有数据库结构变化，未生成升级补丁，每次更新需要全新安装！]

用户支付管理，此插件为[会员尊享插件](https://thinkadmin.top/vip-introduce)，未授权不可商用。

支付类型主要定义为两种类型，分别为：线上支付和线下支持。

* 线下支付包含：账户余额支付、账户积分抵扣。
* 线上支付包含：各种类型的微信支付、种种类型的支付宝支付、大额凭证支付。

目前同一业务订单支付混合支付，由业务系统负责传入对应订单总金额及此次支付金额。
通过已完成的支付总额来判断该业务订单是否已经支付完成，可以在事件回调中进行订单状态刷新处理。

**TODO:** 子支付单后面会支持独立退款操作。

### 开放接口

接口文档：https://documenter.getpostman.com/view/4518676/2s93eeRpDr

### 安装插件

```shell
### 安装前建议尝试更新所有组件
composer update --optimize-autoloader

### 安装稳定版本 ( 插件仅支持在 ThinkAdmin v6.1 中使用 )
composer require zoujingli/think-plugs-payment --optimize-autoloader

### 安装测试版本（ 插件仅支持在 ThinkAdmin v6.1 中使用 ）
composer require zoujingli/think-plugs-payment dev-master --optimize-autoloader
```

### 卸载插件

```shell
### 注意，插件卸载不会删除数据表，需要手动删除
composer remove zoujingli/think-plugs-payment
```

### 插件数据

本插件涉及数据表有：

* 插件-支付-地址：`plugin_payment_address`
* 插件-支付-余额：`plugin_payment_balance`
* 插件-支付-积分：`plugin_payment_integral`
* 插件-支付-配置：`plugin_payment_config`
* 插件-支付-行为：`plugin_payment_record`

### 版权说明

**ThinkPlugsPayment** 为 **ThinkAdmin** 会员插件，未授权不可商用，了解商用授权请阅读 [《会员尊享介绍》](https://thinkadmin.top/vip-introduce)。