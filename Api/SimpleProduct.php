<?php

namespace BigBridge\ProductImport\Api;

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