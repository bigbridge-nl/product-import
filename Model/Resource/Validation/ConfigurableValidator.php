<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\ConfigurableProduct;
use BigBridge\ProductImport\Api\Product;

/**
 * @author Patrick van Bergen
 */
class ConfigurableValidator extends Validator
{
    /**
     * @param ConfigurableProduct $product
     */
    public function validate(Product $product)
    {
        parent::validate($product);

        $skus = [];

        foreach ($product->getVariants() as $variant) {
            if (!$variant->isOk()) {
                $skus[] = $variant->getSku();
            }
        }

        if (!empty($skus)) {
            $product->addError("These variants have errors: " . implode(', ', $skus));
        }
    }
}