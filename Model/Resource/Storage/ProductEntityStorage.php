<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
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
     * @param string[] $skus
     * @return array
     */
    public function getExistingSkus(array $skus)
    {
        if (empty($skus)) {
            return [];
        }

        return $this->db->fetchMap("
            SELECT `sku`, `entity_id` 
            FROM `{$this->metaData->productEntityTable}`
            WHERE BINARY `sku` IN (" . $this->db->getMarks($skus) . ")
        ", array_values($skus));
    }

    /**
     * Returns an sku => id map for all existing products.
     *
     * @param Product[] $products
     * @return array
     */
    public function getExistingProductIds(array $products)
    {
        // collect skus
        $skus = [];
        foreach ($products as $product) {
            $skus[] = $product->getSku();
        }

        return $this->getExistingSkus($skus);
    }

    /**
     * Use this function to create a Product object for an existing product when its type is not given in the import.
     *
     * @param string $sku
     * @return BundleProduct|ConfigurableProduct|DownloadableProduct|GroupedProduct|SimpleProduct|VirtualProduct|false
     */
    public function getExistingProductBySku(string $sku)
    {
        $type = $this->db->fetchSingleCell("
            SELECT `type_id`
            FROM {$this->metaData->productEntityTable}
            WHERE BINARY `sku` = ?
        ", [
            $sku
        ]);

        if (!$type) {
            return false;
        }

        switch ($type) {
            case SimpleProduct::TYPE_SIMPLE:
                $product = new SimpleProduct($sku);
                break;
            case VirtualProduct::TYPE_VIRTUAL:
                $product = new VirtualProduct($sku);
                break;
            case DownloadableProduct::TYPE_DOWNLOADABLE:
                $product = new DownloadableProduct($sku);
                break;
            case GroupedProduct::TYPE_GROUPED:
                $product = new GroupedProduct($sku);
                break;
            case ConfigurableProduct::TYPE_CONFIGURABLE:
                $product = new ConfigurableProduct($sku);
                break;
            case BundleProduct::TYPE_BUNDLE:
                $product = new BundleProduct($sku);
                break;
            default:
                die('Unknown product type: ' . $type);
        }

        return $product;
    }

    /**
     * Use this function to create a Product object for an existing product when its type is not given in the import.
     *
     * @param int $id
     * @return BundleProduct|ConfigurableProduct|DownloadableProduct|GroupedProduct|SimpleProduct|VirtualProduct|false
     */
    public function getExistingProductById(int $id)
    {
        $row = $this->db->fetchRow("
            SELECT `type_id`, `sku`
            FROM {$this->metaData->productEntityTable}
            WHERE `entity_id` = ?
        ", [
            $id
        ]);

        if (!$row) {
            return false;
        }

        $type = $row['type_id'];
        $sku = $row['sku'];

        switch ($type) {
            case SimpleProduct::TYPE_SIMPLE:
                $product = new SimpleProduct($sku);
                break;
            case VirtualProduct::TYPE_VIRTUAL:
                $product = new VirtualProduct($sku);
                break;
            case DownloadableProduct::TYPE_DOWNLOADABLE:
                $product = new DownloadableProduct($sku);
                break;
            case GroupedProduct::TYPE_GROUPED:
                $product = new GroupedProduct($sku);
                break;
            case ConfigurableProduct::TYPE_CONFIGURABLE:
                $product = new ConfigurableProduct($sku);
                break;
            case BundleProduct::TYPE_BUNDLE:
                $product = new BundleProduct($sku);
                break;
            default:
                die('Unknown product type: ' . $type);
        }

        $product->id = $id;

        return $product;
    }

    /**
     * @param Product[] $products
     */
    public function checkIfIdsExist(array $products)
    {
        if (empty($products)) {
            return;
        }

        $productsWithId = [];
        $productIds = [];
        foreach ($products as $product) {
            if ($product->id) {
                $productsWithId[] = $product;
                $productIds[] = $product->id;
            }
        }

        if (empty($productsWithId)) {
            return;
        }

        $exists = $this->db->fetchMap("
            SELECT `entity_id`, `entity_id`
            FROM {$this->metaData->productEntityTable}
            WHERE `entity_id` IN (" . $this->db->getMarks($productIds) . ")
        ", $productIds);

        foreach ($productsWithId as $product) {
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
            $vals[] = (int)$product->getHasOptions();
            $vals[] = (int)$product->getRequiredOptions();
        }

        if (count($vals) > 0) {

            $this->db->insertMultiple($this->metaData->productEntityTable, ['attribute_set_id', 'type_id', 'sku', 'has_options', 'required_options'], $vals,
                Magento2DbConnection::_1_KB);

            // store the new ids with the products
            $sku2id = $this->db->fetchMap("
                SELECT `sku`, `entity_id` 
                FROM `{$this->metaData->productEntityTable}` 
                WHERE BINARY `sku` IN (" . $this->db->getMarks($skus) . ")
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
        if (empty($products)) {
            return;
        }

        $dateTime = date('Y-m-d H:i:s');
        $productIds = array_column($products, 'id');

        $existingValues = $this->db->fetchGrouped("
            SELECT
                `entity_id`, `has_options`, `required_options`, `attribute_set_id`
            FROM {$this->metaData->productEntityTable}
            WHERE `entity_id` IN (" . $this->db->getMarks($productIds) . ")     
        ", $productIds, [
            'entity_id'
        ]);

        $attributeSetUpdates = [];

        foreach ($products as $product) {
            $sku = $product->getSku();
            $type = $product->getType();
            $attributeSetId = $product->getAttributeSetId();
            $hasOptions = $product->getHasOptions();
            $requiredOptions = $product->getRequiredOptions();

            $attributeSetUpdates[] = $product->id;
            $attributeSetUpdates[] = $type;
            $attributeSetUpdates[] = $sku;
            $attributeSetUpdates[] = $attributeSetId !== null ? $attributeSetId : $existingValues[$product->id]['attribute_set_id'];
            $attributeSetUpdates[] = $dateTime;
            $attributeSetUpdates[] = $hasOptions !== null ? (int)$hasOptions : $existingValues[$product->id]['has_options'];
            $attributeSetUpdates[] = $requiredOptions !== null ? (int)$requiredOptions : $existingValues[$product->id]['required_options'];
        }

        $this->db->insertMultipleWithUpdate(
            $this->metaData->productEntityTable,
            ['entity_id', 'type_id', 'sku', 'attribute_set_id', 'updated_at', 'has_options', 'required_options'],
            $attributeSetUpdates,
            Magento2DbConnection::_1_KB,
            "`sku` = VALUES(`sku`), `type_id` = VALUES(`type_id`), `attribute_set_id` = VALUES(`attribute_set_id`), `updated_at`= VALUES(`updated_at`), `has_options` = VALUES(`has_options`), `required_options` = VALUES(`required_options`)");
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
    public function removeOldCategoryIds(array $products)
    {
        if (empty($products)) { return; }

        $productIds = array_column($products, 'id');

        // load existing links
        $rows = $this->db->fetchAllAssoc("
            SELECT `category_id`, `product_id`
            FROM `" . $this->metaData->categoryProductTable . "`
            WHERE `product_id` IN (" . implode(',', $productIds) . ")
        ");

        // collect existing links
        $toBeRemoved = [];
        foreach ($rows as $row) {
            $toBeRemoved[$row['product_id']][$row['category_id']] = true;
        }

        // remove links from this set that are present in the import
        foreach ($products as $product) {
            $categoryIds = $product->getCategoryIds();
            if ($categoryIds === null) {
                // categories are not used in the import; skip
                unset($toBeRemoved[$product->id]);
            } else {
                foreach ($categoryIds as $categoryId) {
                    unset($toBeRemoved[$product->id][$categoryId]);
                }
            }
        }

        // delete links one by one (there won't be many)
        foreach ($toBeRemoved as $productId => $categoryIds) {
            foreach ($categoryIds as $categoryId => $true) {
                $this->db->execute("
                    DELETE FROM `" . $this->metaData->categoryProductTable . "`
                    WHERE `product_id` = ? AND `category_id` = ?
                ", [
                    $productId,
                    $categoryId
                ]);
            }
        }
    }

    /**
     * @param  Product[] $products
     */
    public function removeOldWebsiteIds(array $products)
    {
        if (empty($products)) { return; }

        $productIds = array_column($products, 'id');

        // load existing links
        $rows = $this->db->fetchAllAssoc("
            SELECT `website_id`, `product_id`
            FROM `" . $this->metaData->productWebsiteTable . "`
            WHERE `product_id` IN (" . implode(',', $productIds) . ")
        ");

        // collect existing links
        $toBeRemoved = [];
        foreach ($rows as $row) {
            $toBeRemoved[$row['product_id']][$row['website_id']] = true;
        }

        // remove links from this set that are present in the import
        foreach ($products as $product) {
            $websiteIds = $product->getWebsiteIds();
            if ($websiteIds === null) {
                // websites are not used in the import; skip
                unset($toBeRemoved[$product->id]);
            } else {
                foreach ($websiteIds as $websiteId) {
                    unset($toBeRemoved[$product->id][$websiteId]);
                }
            }
        }

        // delete links one by one (there won't be many)
        foreach ($toBeRemoved as $productId => $websiteIds) {
            foreach ($websiteIds as $websiteId => $true) {
                $this->db->execute("
                    DELETE FROM `" . $this->metaData->productWebsiteTable . "`
                    WHERE `product_id` = ? AND `website_id` = ?
                ", [
                    $productId,
                    $websiteId
                ]);
            }
        }
    }

    /**
     * @param Product[] $products
     */
    public function insertCategoryIds(array $products)
    {
        $values = [];

        foreach ($products as $product) {
            $categoryIds = $product->getCategoryIds();
            if ($categoryIds === null) { continue; }
            foreach ($categoryIds as $categoryId) {
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

    /**
     * @param Product[] $products
     */
    public function removeUrlPaths(array $products)
    {
        $productIds = array_column($products, 'id');
        $attributeId = $this->metaData->productEavAttributeInfo[ProductStoreView::ATTR_URL_PATH]->attributeId;

        $this->db->deleteMultipleWithWhere(
            $this->metaData->productEntityVarcharTable,
            "entity_id", $productIds,
            "attribute_id = {$attributeId}");
    }
}
