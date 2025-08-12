<?php

namespace BigBridge\ProductImport\Helper;

/**
 * @author Patrick van Bergen
 */
class Decimal
{
    const DECIMAL_PATTERN = '/^-?(\d{0,12}\.\d{0,4}|\d{1,12})$/';
    const DECIMAL_FORMAT = "%.4f";

    const DECIMAL_20_6_PATTERN = '/^-?(\d{0,20}\.\d{0,6}|\d{1,20})$/';
    const DECIMAL_20_6_FORMAT = "%.6f";

    public static $decimalPricePattern = self::DECIMAL_PATTERN;
    public static $decimalPriceFormat = self::DECIMAL_FORMAT;

    public static $decimalEavPattern = self::DECIMAL_PATTERN;
    public static $decimalEavFormat = self::DECIMAL_FORMAT;

    /**
     * Formats $in with 4 decimals.
     * If $in is not a number, it is left unchanged.
     *
     * @param string|null $in
     * @return string|null
     */
    public static function format(?string $in = null)
    {
        if ($in === null) {
            return $in;
        } else {

            $in = trim($in);

            if (!preg_match(self::DECIMAL_PATTERN, $in)) {
                return $in;
            } else {
                return sprintf(self::DECIMAL_FORMAT, $in);
            }
        }
    }

    /**
     * Formats $in with 4 / 6 decimals.
     * If $in is not a number, it is left unchanged.
     *
     * @param string|null $in
     * @return string|null
     */
    public static function formatPrice(?string $in = null)
    {
        if ($in === null) {
            return $in;
        } else {

            $in = trim($in);

            if (!preg_match(self::$decimalPricePattern, $in)) {
                return $in;
            } else {
                return sprintf(self::$decimalPriceFormat, $in);
            }
        }
    }
}
