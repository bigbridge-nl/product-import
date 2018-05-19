<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class SimpleProduct extends Product
{
    const TYPE_SIMPLE = 'simple';

    public function getType()
    {
        return self::TYPE_SIMPLE;
    }
}