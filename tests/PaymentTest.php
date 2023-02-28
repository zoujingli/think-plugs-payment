<?php

namespace think\admin\tests;

use PHPUnit\Framework\TestCase;
use plugin\account\service\Account;
use plugin\payment\service\Payment;

class PaymentTest extends TestCase
{
    public function testGetTypes()
    {
        $all = Payment::getTypeAll();
        $this->assertNotEmpty($all);
    }

    public function testGetTypeByChannel()
    {
        $all = Payment::getTypeByChannel(Account::WXAPP);
        $this->assertNotEmpty($all);
    }
}