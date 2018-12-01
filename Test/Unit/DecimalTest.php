<?php

namespace BigBridge\ProductImport\Test\Unit;

use BigBridge\ProductImport\Helper\Decimal;

/**
 * @author Patrick van Bergen
 */
class DecimalTest extends \PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass()
    {
        // include Magento auto-loading
        require_once __DIR__ . '/../../../../../app/autoload.php';
    }

    public function testDecimal()
    {
        $this->assertSame(null, Decimal::format(null));
        $this->assertSame("10.0000", Decimal::format("10"));
        $this->assertSame("10.0000", Decimal::format(" 10"));
        $this->assertSame("10.0000", Decimal::format("10 "));
        $this->assertSame("-10.0000", Decimal::format("-10"));
        $this->assertSame("10.2500", Decimal::format("10.25"));
        $this->assertSame("10.2536", Decimal::format("10.2536"));
        $this->assertSame("0.2536", Decimal::format(".2536"));
        $this->assertSame("three", Decimal::format("three"));
    }
}