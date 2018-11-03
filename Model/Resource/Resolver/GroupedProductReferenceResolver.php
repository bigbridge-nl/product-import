<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;

/**
 * @author Patrick van Bergen
 */
class GroupedProductReferenceResolver
{
    /** @var ProductEntityStorage */
    protected $productEntityStorage;

    public function __construct(
        ProductEntityStorage $productEntityStorage)
    {
        $this->productEntityStorage = $productEntityStorage;
    }

    /**
     * @param GroupedProduct[] $products
     */
    public function resolveIds(array $products)
    {
        /** @var GroupedProduct[] $affectedProducts */
        $affectedProducts = [];
        foreach ($products as $product) {
            if ($product->getMembers() !== null) {
                $affectedProducts[] = $product;
            }
        }

        // collect all member skus
        $memberSkus = [];
        foreach ($affectedProducts as $product) {
            foreach ($product->getMembers() as $member) {
                $memberSkus[] = $member->getSku();
            }
        }

        $memberSkus = array_unique($memberSkus);

        // query all ids at once
        $sku2id = $this->productEntityStorage->getExistingSkus($memberSkus);

        // assign these ids
        foreach ($affectedProducts as $product) {
            foreach ($product->getMembers() as $member) {

                $sku = $member->getSku();

                if (array_key_exists($sku, $sku2id)) {
                    $member->setProductId($sku2id[$sku]);
                } else {
                    $product->addError("Grouped product member with sku " . $sku . " should have been created before, but it cannot be found");
                }
            }
        }
    }
}