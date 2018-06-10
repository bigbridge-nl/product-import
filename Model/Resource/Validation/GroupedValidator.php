<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\GroupedProduct;

/**
 * @author Patrick van Bergen
 */
class GroupedValidator
{
    /**
     * @param GroupedProduct $product
     */
    public function validate(GroupedProduct $product)
    {
        if ($product->id === null && $product->getMembers() === null) {
            $product->addError("Specify the members with setMembers()");
        }
    }
}