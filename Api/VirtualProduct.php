<?php

namespace BigBridge\ProductImport\Api;

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