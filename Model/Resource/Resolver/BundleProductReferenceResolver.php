<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;

/**
 * @author Patrick van Bergen
 */
class BundleProductReferenceResolver
{
    /** @var ProductEntityStorage */
    protected $productEntityStorage;

    public function __construct(
        ProductEntityStorage $productEntityStorage)
    {
        $this->productEntityStorage = $productEntityStorage;
    }

    /**
     * @param BundleProduct[] $products
     * @param ImportConfig $config
     * @throws \Exception
     */
    public function resolveIds(array $products)
    {
        // collect all selection skus
        $selectionSkus = [];
        foreach ($products as $product) {
            if (($options = $product->getOptions()) !== null) {
                foreach ($options as $option) {
                    foreach ($option->getSelections() as $selection) {
                        $selectionSkus[] = $selection->getSku();
                    }

                }
            }
        }

        $selectionSkus = array_unique($selectionSkus);

        // query all ids at once
        $sku2id = $this->productEntityStorage->getExistingSkus($selectionSkus);

        // assign these ids
        foreach ($products as $product) {
            if (($options = $product->getOptions()) !== null) {
                foreach ($options as $option) {
                    foreach ($option->getSelections() as $selection) {

                        $sku = $selection->getSku();

                        if (array_key_exists($sku, $sku2id)) {
                            $selection->setProductId($sku2id[$sku]);
                        } else {
                            $product->addError("Bundle product selection with sku " . $sku . " should have been created before, but it cannot be found");
                        }
                    }
                }
            }
        }
    }
}