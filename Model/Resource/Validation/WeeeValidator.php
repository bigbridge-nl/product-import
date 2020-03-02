<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Helper\Decimal;

class WeeeValidator
{
    /**
     * @param Product $product
     */
    public function validateWeees(Product $product)
    {
        $weees = $product->getWeees();
        if (!$weees) {
            return;
        }

        foreach ($weees as $weee) {
            if (!preg_match(Decimal::DECIMAL_PATTERN, $weee->getValue())) {
                $product->addError("Weee value is not a decimal number with dot (" . $weee->getValue() . ")");
            }
        }
    }

}
