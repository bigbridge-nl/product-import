<?php

namespace BigBridge\ProductImport\Helper;

/**
 * @author Patrick van Bergen
 */
class Decimal
{
    const DECIMAL_PATTERN = '/^-?\d{1,12}(\.\d{0,4})?$/';
    const DECIMAL_FORMAT = "%.4f";

    /**
     * Formats $in with 4 decimals.
     * If $in is not a number, it is left unchanged.
     *
     * @param string|null $in
     * @return string|null
     */
    public static function format(string $in = null)
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
}