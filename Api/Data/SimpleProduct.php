<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class SimpleProduct extends Product
{
    const PLACEHOLDER_NAME = 'Product Placeholder';
    const PLACEHOLDER_PRICE = '123456.78';

    const TYPE_SIMPLE = 'simple';

    public function getType()
    {
        return self::TYPE_SIMPLE;
    }

    /**
     * @return SimpleProduct
     */
    public static function createPlaceholder($sku, $attributeSetId)
    {
        $placeholder = new SimpleProduct($sku);
        $placeholder->placeholder = true;

        $placeholder->setAttributeSetId($attributeSetId);

        $placeholder->global()->setName(self::PLACEHOLDER_NAME);
        $placeholder->global()->setPrice(self::PLACEHOLDER_PRICE);
        $placeholder->global()->setStatus(ProductStoreView::STATUS_DISABLED);

        return $placeholder;
    }
}