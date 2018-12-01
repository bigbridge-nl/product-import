<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class ConfigurableStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    public function performTypeSpecificStorage(array $products)
    {
        $affectedVariantProducts = [];
        $affectedSuperAttributeProducts = [];

        foreach ($products as $product) {
            if ($product->getVariantSkus() !== null) {
                $affectedVariantProducts[] = $product;
            }
            if ($product->getSuperAttributeCodes() !== null) {
                $affectedSuperAttributeProducts[] = $product;
            }
        }

        $this->updateSuperAttributes($affectedVariantProducts);
        $this->updateLinks($affectedSuperAttributeProducts);
        $this->updateRelations($affectedSuperAttributeProducts);
    }

    /**
     * @param ConfigurableProduct[] $products
     */
    protected function updateSuperAttributes(array $products)
    {
        // update by remove and insert
        $this->removeSuperAttributes($products);
        $this->createSuperAttributes($products);
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

            $currentChildIds = $configurable->getVariantIds();

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

            $currentChildIds = $configurable->getVariantIds();

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

    /**
     * @param Product[] $products
     */
    public function removeLinkedVariants(array $products)
    {
        $productIds = array_column($products, 'id');

        $this->removeSuperAttributes($products);

        $this->db->deleteMultiple($this->metaData->superLinkTable, 'parent_id', $productIds);
        $this->db->deleteMultiple($this->metaData->relationTable, 'parent_id', $productIds);
    }
}