<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;

/**
 * @author Patrick van Bergen
 */
class GroupedValidator
{
    /**
     * @param GroupedProduct $product
     * @param Product[] $batchProducts
     */
    public function validate(GroupedProduct $product, array $batchProducts)
    {
        if ($product->id === null && $product->getMembers() === null) {
            $product->addError("Specify the members with setMembers()");
        }

        if ($product->getMembers() !== null) {
            foreach ($product->getMembers() as $member) {
                $memberSku = $member->getSku();
                if (array_key_exists($memberSku, $batchProducts)) {
                    if (!$batchProducts[$memberSku]->isOk()) {
                        $product->addError("A member product is invalid: " . $memberSku);
                        break;
                    }
                }
            }
        }
    }
}
