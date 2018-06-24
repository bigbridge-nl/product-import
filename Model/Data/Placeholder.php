<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
use Exception;

/**
 * @author Patrick van Bergen
 */
abstract class Placeholder extends Product
{
    const PLACEHOLDER_NAME = 'Product Placeholder';
    const PLACEHOLDER_PRICE = '123456.7800';

    /**
     * @param string $sku
     * @param int $attributeSetId
     * @return VirtualProduct
     */
    public static function createPlaceholder(string $sku, int $attributeSetId)
    {
        $placeholder = new VirtualProduct($sku);
        $placeholder->placeholder = true;

        $placeholder->setAttributeSetId($attributeSetId);

        $placeholder->global()->setName(self::PLACEHOLDER_NAME);
        $placeholder->global()->setPrice(self::PLACEHOLDER_PRICE);
        $placeholder->global()->setStatus(ProductStoreView::STATUS_DISABLED);

        return $placeholder;
    }

    /**
     * Used in catalog_product_entity table
     * @return string
     * @throws Exception
     */
    public function getType()
    {
        throw new Exception("No object of type placeholder should be created");
    }
}