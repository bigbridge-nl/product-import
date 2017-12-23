<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\SimpleProduct;

/**
 * @author Patrick van Bergen
 */
class SimpleStorage extends ProductStorage
{
    public function getType()
    {
        return 'simple';
    }

    /**
     * @param SimpleProduct[] $insertProducts
     * @param SimpleProduct[] $updateProducts
     */
    public function performTypeSpecificStorage(array $insertProducts, array $updateProducts)
    {
    }
}