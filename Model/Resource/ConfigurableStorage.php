<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\Resolver\ReferenceResolver;
use BigBridge\ProductImport\Model\Resource\Resolver\UrlKeyGenerator;
use BigBridge\ProductImport\Model\Resource\Storage\ImageStorage;
use BigBridge\ProductImport\Model\Resource\Storage\LinkedProductStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use BigBridge\ProductImport\Model\Resource\Storage\StockItemStorage;
use BigBridge\ProductImport\Model\Resource\Storage\TierPriceStorage;
use BigBridge\ProductImport\Model\Resource\Storage\UrlRewriteStorage;
use BigBridge\ProductImport\Model\Resource\Validation\ConfigurableValidator;

/**
 * @author Patrick van Bergen
 */
class ConfigurableStorage extends ProductStorage
{
    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        ConfigurableValidator $validator,
        ReferenceResolver $referenceResolver,
        UrlKeyGenerator $urlKeyGenerator,
        UrlRewriteStorage $urlRewriteStorage,
        ProductEntityStorage $productEntityStorage,
        ImageStorage $imageStorage,
        LinkedProductStorage $linkedProductStorage,
        TierPriceStorage $tierPriceStorage,
        StockItemStorage $stockItemStorage)
    {
        parent::__construct($db, $metaData, $validator, $referenceResolver, $urlKeyGenerator, $urlRewriteStorage, $productEntityStorage, $imageStorage, $linkedProductStorage, $tierPriceStorage, $stockItemStorage);
    }

    /**
     * @param ConfigurableProduct[] $insertProducts
     * @param ConfigurableProduct[] $updateProducts
     */
    public function performTypeSpecificStorage(array $insertProducts, array $updateProducts)
    {
        $this->createSuperAttributes($insertProducts);
        $this->createLinks($insertProducts);
        $this->createRelations($insertProducts);

        $this->updateSuperAttributes($updateProducts);
        $this->updateLinks($updateProducts);
        $this->updateRelations($updateProducts);
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    protected function updateSuperAttributes(array $products)
    {
        // collect products whose super attribute configuration has changed (which is very rare)
        $changedSuperAttributeProducts = $this->collectChangedSuperAttributes($products);

        // update by remove and insert
        $this->removeSuperAttributes($changedSuperAttributeProducts);
        $this->createSuperAttributes($changedSuperAttributeProducts);
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    protected function createSuperAttributes(array $products)
    {
        foreach ($products as $product) {
            foreach ($product->getSuperAttributeCodes() as $i => $attributeCode) {

                $this->db->execute("
                    INSERT INTO {$this->metaData->superAttributeTable}
                    SET product_id = ?, attribute_id = ?, position = ?
                ", [
                    $product->id,
                    $this->metaData->productEavAttributeInfo[$attributeCode]->attributeId,
                    $i
                ]);

                $this->db->execute("
                    INSERT INTO {$this->metaData->superAttributeLabelTable}
                    SET product_super_attribute_id = ?, store_id = 0, use_default = 0, value = ?
                ", [
                    $this->db->getLastInsertId(),
                    ucwords(str_replace('_', ' ', $attributeCode))
                ]);
            }
        }
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    protected function createLinks(array $products)
    {
        $values = [];

        foreach ($products as $product) {
            foreach ($product->getVariants() as $variant) {
                $values[] = $variant->id;
                $values[] = $product->id;
            }
        }

        $this->db->insertMultiple($this->metaData->superLinkTable, ['product_id', 'parent_id'], $values, Magento2DbConnection::_1_KB);
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    protected function createRelations(array $products)
    {
        $values = [];

        foreach ($products as $product) {
            foreach ($product->getVariants() as $variant) {
                $values[] = $product->id;
                $values[] = $variant->id;
            }
        }

        $this->db->insertMultiple($this->metaData->relationTable, ['parent_id', 'child_id'], $values, Magento2DbConnection::_1_KB);
    }

    /**
     * @param ConfigurableProduct[] $products
     * @return array
     */
    protected function collectChangedSuperAttributes(array $products)
    {
        if (empty($products)) {
            return [];
        }

        $changedSuperAttributeProducts = [];
        $productIds = array_column($products, 'id');

        // check for changes
        $storedAttributes = $this->db->fetchMap("
            SELECT product_id, GROUP_CONCAT(attribute_id ORDER BY attribute_id ASC SEPARATOR ' ')
            FROM {$this->metaData->superAttributeTable}
            WHERE product_id IN (" . $this->db->getMarks($productIds) . ")
            GROUP BY product_id
        ", $productIds);

        foreach ($products as $product) {

            // create a string with sorted super attribute ids
            $superAttributeCodes = $product->getSuperAttributeCodes();
            $serializedAttributeIds =  [];
            foreach ($superAttributeCodes as $attributeCode) {
                $serializedAttributeIds[] = $this->metaData->productEavAttributeInfo[$attributeCode]->attributeId;
            }
            sort($serializedAttributeIds);
            $serializedAttributeIds = implode(' ', $serializedAttributeIds);

            // check for changes
            if (!array_key_exists($product->id, $storedAttributes) || ($storedAttributes[$product->id]) !== $serializedAttributeIds) {
                $changedSuperAttributeProducts[] = $product;
            }
        }

        return $changedSuperAttributeProducts;
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    protected function removeSuperAttributes(array $products)
    {
        $productIds = array_column($products, 'id');

        $this->db->deleteMultiple($this->metaData->superAttributeTable, 'product_id', $productIds);
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    protected function updateLinks(array $products)
    {
        if (empty($products)) {
            return;
        }

        $configurableIds = array_column($products, 'id');

        $rows = $this->db->fetchAllNonAssoc("
            SELECT parent_id, product_id 
            FROM {$this->metaData->superLinkTable}
            WHERE parent_id in (" . $this->db->getMarks($configurableIds) . ")
        ", $configurableIds);

        $existingVariantIds = [];

        foreach ($configurableIds as $configurableId) {
            $existingVariantIds[$configurableId] = [];
        }
        foreach ($rows as list($configurableIds, $variantId)) {
            $existingVariantIds[$configurableIds][] = $variantId;
        }

        foreach ($products as $configurable) {

            $configurableId = $configurable->id;

            $currentChildIds = [];
            foreach ($configurable->getVariants() as $variant) {
                $currentChildIds[] = $variant->id;
            }

            $added = array_diff($currentChildIds, $existingVariantIds[$configurableId]);
            $removed = array_diff($existingVariantIds[$configurableId], $currentChildIds);

            // don't bother compounding queries; addition and removal of variants is rare

            foreach ($added as $variantId) {
                $this->db->execute("
                    INSERT INTO {$this->metaData->superLinkTable} 
                    SET product_id = ?, parent_id = ?
                ", [
                    $variantId,
                    $configurableId
                ]);
            }

            foreach ($removed as $variantId) {
                $this->db->execute("
                    DELETE FROM {$this->metaData->superLinkTable} 
                    WHERE product_id = ? AND parent_id = ?
                ", [
                    $variantId,
                    $configurableId
                ]);
            }
        }
    }


    /**
     * @param ConfigurableProduct[] $products
     */
    protected function updateRelations(array $products)
    {
        if (empty($products)) {
            return;
        }

        $configurableIds = array_column($products, 'id');

        $rows = $this->db->fetchAllNonAssoc("
            SELECT parent_id, child_id 
            FROM {$this->metaData->relationTable}
            WHERE parent_id in (" . $this->db->getMarks($configurableIds) . ")
        ", $configurableIds);

        $existingVariantIds = [];

        foreach ($configurableIds as $configurableId) {
            $existingVariantIds[$configurableId] = [];
        }
        foreach ($rows as list($configurableIds, $variantId)) {
            $existingVariantIds[$configurableIds][] = $variantId;
        }

        foreach ($products as $configurable) {

            $configurableId = $configurable->id;

            $currentChildIds = [];
            foreach ($configurable->getVariants() as $variant) {
                $currentChildIds[] = $variant->id;
            }

            $added = array_diff($currentChildIds, $existingVariantIds[$configurableId]);
            $removed = array_diff($existingVariantIds[$configurableId], $currentChildIds);

            // don't bother compounding queries; addition and removal of variants is rare

            foreach ($added as $variantId) {
                $this->db->execute("
                    INSERT INTO {$this->metaData->relationTable} 
                    SET child_id = ?, parent_id = ?
                ", [
                    $variantId,
                    $configurableId
                ]);
            }

            foreach ($removed as $variantId) {
                $this->db->execute("
                    DELETE FROM {$this->metaData->relationTable} 
                    WHERE child_id = ? AND parent_id = ?
                ", [
                    $variantId,
                    $configurableId
                ]);
            }
        }
    }
}