<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class SimpleProduct extends Product
{
    public function getType()
    {
        return 'simple';
    }
}