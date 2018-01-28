<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\Product;

/**
 * @author Patrick van Bergen
 */
class BundleStorage extends ProductStorage
{

    /**
     * @param Product[] $insertProducts
     * @param Product[] $updateProducts
     */
    public function performTypeSpecificStorage(array $insertProducts, array $updateProducts)
    {
        // TODO: Implement performTypeSpecificStorage() method.
    }
}