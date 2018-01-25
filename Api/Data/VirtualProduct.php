<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class VirtualProduct extends SimpleProduct
{
    public function getType()
    {
        return 'virtual';
    }
}