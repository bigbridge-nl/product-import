<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use Exception;

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
     * @throws \Exception
     */
    public function resolveIds(array $products)
    {
        // collect all member skus
        $memberSkus = [];
        foreach ($products as $product) {
            foreach ($product->getMembers() as $member) {
                $memberSkus[] = $member->getSku();
            }
        }

        $memberSkus = array_unique($memberSkus);

        // query all ids at once
        $sku2id = $this->productEntityStorage->getExistingSkus($memberSkus);

        // assign these ids
        foreach ($products as $product) {
            foreach ($product->getMembers() as $member) {

                $sku = $member->getSku();

                if (array_key_exists($sku, $sku2id)) {
                    $member->setProductId($sku2id[$sku]);
                } else {
                    throw new Exception("Grouped product member with sku " . $sku . " should have been created before, but it cannot be found");
                }
            }
        }
    }
}