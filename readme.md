# ThinkPlugsPayment for ThinkAdmin

[![Latest Stable Version](https://poser.pugx.org/zoujingli/think-plugs-payment/v/stable)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![Latest Unstable Version](https://poser.pugx.org/zoujingli/think-plugs-payment/v/unstable)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![Total Downloads](https://poser.pugx.org/zoujingli/think-plugs-payment/downloads)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![Monthly Downloads](https://poser.pugx.org/zoujingli/think-plugs-payment/d/monthly)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![Daily Downloads](https://poser.pugx.org/zoujingli/think-plugs-payment/d/daily)](https://packagist.org/packages/zoujingli/think-plugs-payment)
[![PHP Version](https://doc.thinkadmin.top/static/icon/php-7.1.svg)](https://thinkadmin.top)
[![License](https://doc.thinkadmin.top/static/icon/license-vip.svg)](https://thinkadmin.top/vip-introduce)

**注意：** 该插件测试版有数据库结构变化，未生成补丁，需要全新安装！]

用户支付管理，此插件为[会员尊享插件](https://thinkadmin.top/vip-introduce)，未授权不可商用。

文档整理中...

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