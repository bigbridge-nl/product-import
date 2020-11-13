<?php

namespace BigBridge\ProductImport\Test\Unit;

use BigBridge\ProductImport\Helper\Decimal;

/**
 * @author Patrick van Bergen
 */
class DecimalTest extends \PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass(): void
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

    public function testPriceDecimal()
    {
        $oldFormat = Decimal::$decimalPriceFormat;
        $oldPattern = Decimal::$decimalPricePattern;

        Decimal::$decimalPriceFormat = Decimal::DECIMAL_20_6_FORMAT;
        Decimal::$decimalPricePattern = Decimal::DECIMAL_20_6_PATTERN;

        $this->assertSame("10.123450", Decimal::formatPrice("10.12345"));

        Decimal::$decimalPriceFormat = Decimal::DECIMAL_FORMAT;
        Decimal::$decimalPricePattern = Decimal::DECIMAL_PATTERN;

        $this->assertSame("10.1230", Decimal::formatPrice("10.123"));

        Decimal::$decimalPriceFormat = $oldFormat;
        Decimal::$decimalPricePattern = $oldPattern;
    }
}
