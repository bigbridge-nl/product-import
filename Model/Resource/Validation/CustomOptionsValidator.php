<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;

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
            $values = $customOption->getValues();
            if ($values) {
                $count = count($values);
                foreach ($product->getStoreViews() as $storeViewCode => $storeView) {
                    $storeViewValues = $storeView->getCustomOptionValues($customOption);
                    $storeViewCount = count($storeViewValues);
                    $error = false;
                    if ($storeViewCode === Product::GLOBAL_STORE_VIEW_CODE) {
                        if ($storeViewCount != $count) {
                            $error = true;
                        }
                    } else {
                        if (($storeViewCount > 0) && ($storeViewCount != $count)) {
                            $error = true;
                        }
                    }
                    if ($error) {
                        $product->addError("Custom option with values [" . implode(', ', $values) . "] has an incorrect number of values in store view '{$storeViewCode}'");
                    }
                }
            }
        }
    }
}