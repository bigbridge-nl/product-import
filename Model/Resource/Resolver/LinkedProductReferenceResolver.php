<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;

/**
 * @author Patrick van Bergen
 */
class LinkedProductReferenceResolver
{
    /**  @var Magento2DbConnection */
    protected $db;

    /** @var ProductEntityStorage */
    protected $productEntityStorage;

    public function __construct(Magento2DbConnection $db, ProductEntityStorage $productEntityStorage)
    {
        $this->db = $db;
        $this->productEntityStorage = $productEntityStorage;
    }

    /**
     * @param Product[] $products
     */
    public function resolveLinkedProductReferences(array $products)
    {
        // collect all linked product skus
        $allLinkedSkus = [];

        foreach ($products as $product) {
            foreach ($product->getLinkedProductSkus() as $skuArray) {
                $allLinkedSkus = array_merge($allLinkedSkus, $skuArray);
            }
        }

        $allLinkedSkus = array_unique($allLinkedSkus);

        // query all ids at once
        $sku2id = $this->productEntityStorage->getExistingSkus($allLinkedSkus);

        // assign these ids
        foreach ($products as $product) {

            foreach ($product->getLinkedProductSkus() as $linkType => $skuArray) {

                $ids = [];

                foreach ($skuArray as $sku) {
                    if (array_key_exists($sku, $sku2id)) {
                        $ids[] = $sku2id[$sku];
                    } else {
                        $product->addError("Referenced product with sku " . $sku . " should have been created before, but it cannot be found");
                    }
                }

                switch ($linkType) {
                    case LinkInfo::RELATED:
                        $product->setRelatedProductId($ids);
                        break;
                    case LinkInfo::UP_SELL:
                        $product->setUpSellProductIds($ids);
                        break;
                    case LinkInfo::CROSS_SELL:
                        $product->setCrossSellProductIds($ids);
                        break;
                }
            }
        }
    }
}