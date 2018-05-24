<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class ProductEntityStorage
{
    /**  @var Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * Returns an sku => id map for all existing skus.
     *
     * @param array $skus
     * @return array
     */
    public function getExistingSkus(array $skus)
    {
        if (count($skus) == 0) {
            return [];
        }

        return $this->db->fetchMap("
            SELECT `sku`, `entity_id` 
            FROM `{$this->metaData->productEntityTable}`
            WHERE `sku` IN (" . $this->db->getMarks($skus) . ")
        ", array_values($skus));
    }

    /**
     * @param Product[] $products
     */
    public function checkIfIdsExist(array $products)
    {
        if (empty($products)) {
            return;
        }

        $productIds = array_column($products, 'id');

        $exists = $this->db->fetchMap("
            SELECT `entity_id`, `entity_id`
            FROM {$this->metaData->productEntityTable}
            WHERE `entity_id` IN (" . $this->db->getMarks($productIds) . ")
        ", $productIds);

        foreach ($products as $product) {
            if (!array_key_exists($product->id, $exists)) {
                $product->addError("Id does not belong to existing product: " . $product->id);
            }
        }
    }

    /**
     * @param Product[] $products
     */
    public function insertMainTable(array $products)
    {
        $skus = [];
        $vals = [];

        foreach ($products as $product) {

            $sku = $product->getSku();

            // index with sku to prevent creation of multiple products with the same sku
            // (this happens when products with different store views are inserted at once)
            if (array_key_exists($sku, $skus)) {
                continue;
            }

            $skus[$sku] = $sku;

            $vals[] = $product->getAttributeSetId();
            $vals[] = $product->getType();
            $vals[] = $sku;
            $vals[] = $product->getHasOptions();
            $vals[] = $product->getRequiredOptions();
        }

        if (count($vals) > 0) {

            $this->db->insertMultiple($this->metaData->productEntityTable, ['attribute_set_id', 'type_id', 'sku', 'has_options', 'required_options'], $vals,
                Magento2DbConnection::_1_KB);

            // store the new ids with the products
            $sku2id = $this->db->fetchMap("
                SELECT `sku`, `entity_id` 
                FROM `{$this->metaData->productEntityTable}` 
                WHERE `sku` IN (" . $this->db->getMarks($skus) . ")
            ", array_values($skus));

            foreach ($products as $product) {
                $product->id = $sku2id[$product->getSku()];
            }
        }
    }

    /**
     * @param Product[] $products
     */
    public function updateMainTable(array $products)
    {
        $dateTime = date('Y-m-d H:i:s');

        $attributeSetUpdates = [];
        $otherUpdates = [];
        foreach ($products as $product) {
            $sku = $product->getSku();
            $type = $product->getType();
            $attributeSetId = $product->getAttributeSetId();
            if ($attributeSetId !== null) {
                $attributeSetUpdates[] = $product->id;
                $attributeSetUpdates[] = $type;
                $attributeSetUpdates[] = $sku;
                $attributeSetUpdates[] = $attributeSetId;
                $attributeSetUpdates[] = $dateTime;
            } else {
                $otherUpdates[] = $product->id;
                $otherUpdates[] = $type;
                $otherUpdates[] = $sku;
                $otherUpdates[] = $dateTime;
            }
        }

        $this->db->insertMultipleWithUpdate($this->metaData->productEntityTable, ['entity_id', 'type_id', 'sku', 'attribute_set_id', 'updated_at'], $attributeSetUpdates,
            Magento2DbConnection::_1_KB, "`sku` = VALUES(`sku`), `type_id` = VALUES(`type_id`), `attribute_set_id` = VALUES(`attribute_set_id`), `updated_at`= VALUES(`updated_at`)");

        $this->db->insertMultipleWithUpdate($this->metaData->productEntityTable, ['entity_id', 'type_id', 'sku', 'updated_at'], $otherUpdates,
            Magento2DbConnection::_1_KB, "`sku` = VALUES(`sku`), `type_id` = VALUES(`type_id`), `updated_at`= VALUES(`updated_at`)");
    }

    /**
     * @param ProductStoreView[] $storeViews
     * @param string $eavAttribute
     */
    public function insertEavAttribute(array $storeViews, string $eavAttribute)
    {
        $attributeInfo = $this->metaData->productEavAttributeInfo[$eavAttribute];
        $tableName = $attributeInfo->tableName;
        $attributeId = $attributeInfo->attributeId;

        if ($attributeInfo->backendType == EavAttributeInfo::TYPE_TEXT) {
            $magnitude = Magento2DbConnection::_128_KB;
        } else {
            $magnitude = Magento2DbConnection::_1_KB;
        }

        $values = [];

        foreach ($storeViews as $storeView) {
            $values[] = $storeView->parent->id;
            $values[] = $attributeId;
            $values[] = $storeView->getStoreViewId();
            $values[] = $storeView->getAttribute($eavAttribute);
        }

        $this->db->insertMultipleWithUpdate($tableName, ['entity_id', 'attribute_id', 'store_id', 'value'], $values,
            $magnitude, "`value` = VALUES(`value`)");
    }

    /**
     * @param array $storeViews
     * @param string $eavAttribute
     */
    public function removeEavAttribute(array $storeViews, string $eavAttribute)
    {
        $attributeInfo = $this->metaData->productEavAttributeInfo[$eavAttribute];
        $tableName = $attributeInfo->tableName;
        $attributeId = $attributeInfo->attributeId;

        foreach ($storeViews as $storeView) {
            $this->db->execute("
                DELETE FROM `" . $tableName . "`
                WHERE `entity_id` = ? AND `attribute_id` = ? AND `store_id` = ?
            ", [
                    $storeView->parent->id,
                    $attributeId,
                    $storeView->getStoreViewId()
            ]);
        }
    }

    /**
     * @param Product[] $products
     */
    public function insertCategoryIds(array $products)
    {
        $values = [];

        foreach ($products as $product) {
            foreach ($product->getCategoryIds() as $categoryId) {
                $values[] = $categoryId;
                $values[] = $product->id;
            }
        }

        // IGNORE serves two purposes:
        // 1. do not fail if the product-category link already existed
        // 2. do not fail if the category does not exist

        $this->db->insertMultipleWithIgnore($this->metaData->categoryProductTable, ['category_id', 'product_id'], $values, Magento2DbConnection::_1_KB);
    }

    /**
     * @param Product[] $products
     */
    public function insertWebsiteIds(array $products)
    {
        $values = [];

        foreach ($products as $product) {
            foreach ($product->getWebsiteIds() as $websiteId) {
                $values[] = $product->id;
                $values[] = $websiteId;
            }
        }

        // IGNORE serves two purposes:
        // 1. do not fail if the product-website link already existed
        // 2. do not fail if the website does not exist

        $this->db->insertMultipleWithIgnore($this->metaData->productWebsiteTable, ['product_id', 'website_id'], $values, Magento2DbConnection::_1_KB);
    }
}