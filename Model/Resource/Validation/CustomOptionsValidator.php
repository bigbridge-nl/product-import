<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\Product;

/**
 * @author Patrick van Bergen
 */
class CustomOptionsValidator
{
    /**
     * @param Product $product
     */
    public function validateCustomOptions(Product $product)
    {
        $customOptions = $product->getCustomOptions();
        if (!$customOptions) {
            return;
        }

        foreach ($customOptions as $customOption) {
            $valueSkus = $customOption->getValueSkus();
            if ($valueSkus) {
                foreach ($product->getStoreViews() as $storeViewCode => $storeView) {
                    $storeViewSkus = [];
                    foreach ($storeView->getCustomOptionValues() as $customOptionValue) {
                        if ($customOptionValue->getCustomOption() !== $customOption) {
                            continue;
                        }
                        $sku = $customOptionValue->getSku();
                        $storeViewSkus[$sku] = $sku;
                    }
                    if (count($storeViewSkus) != count($valueSkus)) {
                        $product->addError("Custom option with values [" . implode(', ', $valueSkus) . "] has an incorrect number of values in store view '{$storeViewCode}'");
                    }
                }
            }
        }
    }
}