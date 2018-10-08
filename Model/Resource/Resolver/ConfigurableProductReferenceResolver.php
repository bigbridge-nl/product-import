<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;

/**
 * @author Patrick van Bergen
 */
class ConfigurableProductReferenceResolver
{
    /** @var ProductEntityStorage */
    protected $productEntityStorage;

    public function __construct(
        ProductEntityStorage $productEntityStorage)
    {
        $this->productEntityStorage = $productEntityStorage;
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    public function resolveIds(array $products)
    {
        $affectedProducts = [];
        foreach ($products as $product) {
            if ($product->getVariantSkus() !== null) {
                $affectedProducts[] = $product;
            }
        }

        $variantSkus = [];
        foreach ($affectedProducts as $product) {
            $variantSkus = array_merge($variantSkus, $product->getVariantSkus());
        }

        // query all ids at once
        $sku2id = $this->productEntityStorage->getExistingSkus($variantSkus);

        // assign these ids
        foreach ($affectedProducts as $product) {

            $variantIds = [];

            foreach ($product->getVariantSkus() as $sku) {

                if (array_key_exists($sku, $sku2id)) {
                    $variantIds[] = $sku2id[$sku];
                } else {
                    $product->addError("Configurable product variant with sku " . $sku . " should have been created before, but it cannot be found");
                }
            }

            $product->setVariantIds($variantIds);
        }
    }
}