<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class VirtualProduct extends SimpleProduct
{
    const TYPE_VIRTUAL = 'virtual';

    public function getType()
    {
        return self::TYPE_VIRTUAL;
    }
}