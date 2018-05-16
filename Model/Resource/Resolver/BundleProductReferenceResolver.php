<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use Exception;

/**
 * @author Patrick van Bergen
 */
class BundleProductReferenceResolver extends ReferenceResolver
{
    /** @var ProductEntityStorage */
    protected $productEntityStorage;

    public function __construct(
        CategoryImporter $categoryImporter,
        TaxClassResolver $taxClassResolver,
        AttributeSetResolver $attributeSetResolver,
        StoreViewResolver $storeViewResolver,
        WebsiteResolver $websiteResolver,
        OptionResolver $optionResolver,
        LinkedProductReferenceResolver $linkedProductReferenceResolver,
        TierPriceResolver $tierPriceResolver,
        ProductEntityStorage $productEntityStorage)
    {
        parent::__construct($categoryImporter, $taxClassResolver, $attributeSetResolver, $storeViewResolver, $websiteResolver, $optionResolver, $linkedProductReferenceResolver, $tierPriceResolver);

        $this->productEntityStorage = $productEntityStorage;
    }

    /**
     * @param BundleProduct[] $products
     * @param ImportConfig $config
     * @throws \Exception
     */
    public function resolveIds(array $products, ImportConfig $config)
    {
        parent::resolveIds($products, $config);

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
                            throw new Exception("Bundle product selection with sku " . $sku . " should have been created before, but it cannot be found");
                        }
                    }
                }
            }
        }
    }
}