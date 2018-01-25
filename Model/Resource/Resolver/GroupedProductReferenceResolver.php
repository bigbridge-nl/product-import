<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use Exception;

/**
 * @author Patrick van Bergen
 */
class GroupedProductReferenceResolver extends ReferenceResolver
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
     * @param GroupedProduct[] $products
     * @param ImportConfig $config
     * @throws \Exception
     */
    public function resolveIds(array $products, ImportConfig $config)
    {
        parent::resolveIds($products, $config);

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