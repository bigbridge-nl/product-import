<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Api\ProductStoreView;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Api\SimpleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Resolver\ReferenceResolver;
use BigBridge\ProductImport\Model\Resource\Resolver\UrlKeyGenerator;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;
use Exception;

/**
 * @author Patrick van Bergen
 */
class SimpleStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    /** @var Validator */
    protected $validator;

    /** @var  ReferenceResolver */
    protected $referenceResolver;

    /** @var UrlKeyGenerator */
    protected $urlKeyGenerator;

    /** @var UrlRewriteStorage */
    protected $urlRewriteStorage;

    /** @var ImageStorage */
    protected $imageStorage;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        Validator $validator,
        ReferenceResolver $referenceResolver,
        UrlKeyGenerator $urlKeyGenerator,
        UrlRewriteStorage $urlRewriteStorage,
        ImageStorage $imageStorage)
    {
        $this->db = $db;
        $this->metaData = $metaData;
        $this->validator = $validator;
        $this->referenceResolver = $referenceResolver;
        $this->urlKeyGenerator = $urlKeyGenerator;
        $this->urlRewriteStorage = $urlRewriteStorage;
        $this->imageStorage = $imageStorage;
    }

    /**
     * @param SimpleProduct[] $simpleProducts
     * @param ImportConfig $config
     * @param ValueSerializer $valueSerializer
     * @throws Exception
     */
    public function storeSimpleProducts(array $simpleProducts, ImportConfig $config, ValueSerializer $valueSerializer)
    {
        // connect store view to product
        $this->setupStoreViewWiring($simpleProducts);

        // collect skus
        $skus = [];
        foreach ($simpleProducts as $product) {
            $skus[] = $product->getSku();
        }

        // find existing products ids from their skus
        $sku2id = $this->getExistingSkus($skus);

        // separate new products from existing products and assign id
        $insertProducts = $updateProducts = [];
        foreach ($simpleProducts as $product) {

            if (array_key_exists($product->getSku(), $sku2id)) {
                $product->id = $sku2id[$product->getSku()];
                $updateProducts[] = $product;
            } else {
                $insertProducts[] = $product;
            }
        }

        // set default values for new products
        $this->setDefaultValues($insertProducts);

        // replace Reference(s) with ids, changes $product->errors
        foreach ($simpleProducts as $product) {
            $this->referenceResolver->resolveIds($product, $config);
        }

        // create url keys based on name and id
        // changes $product->errors
        $this->urlKeyGenerator->createUrlKeysForNewProducts($insertProducts, $config->urlKeyScheme, $config->duplicateUrlKeyStrategy);

        $this->urlKeyGenerator->createUrlKeysForExistingProducts($updateProducts, $config->urlKeyScheme, $config->duplicateUrlKeyStrategy);

        $validProducts = [];

        foreach ($simpleProducts as $product) {

            // checks all attributes, changes $product->errors
            $this->validator->validate($product);

            if (!$product->isOk()) {
                continue;
            }

            // collect valid products
            $validProducts[] = $product;
        }

        // in a "dry run" no actual imports to the database are done
        if (!$config->dryRun) {

            $this->saveProducts($validProducts, $valueSerializer);
        }

        // call user defined functions to let them process the results
        foreach ($config->resultCallbacks as $callback) {
            foreach ($simpleProducts as $product) {
                call_user_func($callback, $product);
            }
        }

        // disconnect store view to product
        $this->tearDownStoreViewWiring($simpleProducts);
    }

    /**
     * Connect product to store view
     *
     * @param Product[] $simpleProducts
     */
    protected function setupStoreViewWiring(array $simpleProducts)
    {
        foreach ($simpleProducts as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                $storeView->parent = $product;
            }
        }
    }

    /**
     * Help the garbage collector by removing cyclic dependencies
     *
     * @param Product[] $simpleProducts
     */
    protected function tearDownStoreViewWiring(array $simpleProducts)
    {
        foreach ($simpleProducts as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                $storeView->parent = null;
            }
        }
    }

    /**
     * @param SimpleProduct[] $insertProducts
     */
    protected function setDefaultValues(array $insertProducts)
    {
        foreach ($insertProducts as $product) {

            // attribute set: Default
            if ($product->getAttributeSetId() === null) {
                $product->setAttributeSetByName("Default");
            }

            $global = $product->global();
            $attributes = $global->getAttributes();

            // visibility: both
            if (!array_key_exists(ProductStoreView::ATTR_VISIBILITY, $attributes)) {
                $global->setVisibility(ProductStoreView::VISIBILITY_BOTH);
            }
            // status: disabled
            if (!array_key_exists(ProductStoreView::ATTR_STATUS, $attributes)) {
                $global->setStatus(ProductStoreView::STATUS_DISABLED);
            }
            // tax class: Taxable Goods
            if (!array_key_exists(ProductStoreView::ATTR_TAX_CLASS_ID, $attributes)) {
                $global->setTaxClassName("Taxable Goods");
            }
        }
    }

    /**
     * @param Product[] $validProducts
     * @param ValueSerializer $valueSerializer
     * @throws Exception
     */
    protected function saveProducts(array $validProducts, ValueSerializer $valueSerializer)
    {
        $validUpdateProducts = $validInsertProducts = [];
        $productsByAttribute = [];
        $productsWithCategories = [];
        $productsWithWebsites = [];

        foreach ($validProducts as $product) {

            // collect valid new and existing products
            if ($product->id !== null) {
                $validUpdateProducts[] = $product;
            } else {
                $validInsertProducts[] = $product;
            }

            if ($product->getCategoryIds() !== []) {
                $productsWithCategories[] = $product;
            }

            if ($product->getWebsiteIds() !== []) {
                $productsWithWebsites[] = $product;
            }

            foreach ($product->getStoreViews() as $storeView) {
                foreach ($storeView->getAttributes() as $key => $value) {
                    $productsByAttribute[$key][] = $storeView;
                }
            }
        }

        $this->db->execute("START TRANSACTION");

        $existingValues = $this->getExistingProductValues($validUpdateProducts);

        try {

            $this->insertMainTable($validInsertProducts);
            $this->updateMainTable($validUpdateProducts);

            foreach ($productsByAttribute as $eavAttribute => $products) {
                $this->insertEavAttribute($products, $eavAttribute);
            }

            $this->insertCategoryIds($productsWithCategories);
            $this->insertWebsiteIds($productsWithWebsites);
            $this->insertStockItems($validProducts);

            $this->imageStorage->storeProductImages($validProducts);

            // url_rewrite (must be done after url_key and category_id)
            $this->urlRewriteStorage->insertRewrites($validInsertProducts, $valueSerializer);
            $this->urlRewriteStorage->updateRewrites($validUpdateProducts, $existingValues, $valueSerializer);

            $this->db->execute("COMMIT");

        } catch (Exception $e) {

            // rollback the transaction
            try { $this->db->execute("ROLLBACK"); } catch (Exception $f) {}

            // let the application handle the exception
            throw $e;
        }
    }

    protected function getExistingProductValues(array $products)
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_column($products, 'id');

        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        $existingData = $this->db->fetchAllAssoc("
            SELECT URL_KEY.`entity_id` as product_id, URL_KEY.`value` AS url_key, GROUP_CONCAT(PG.`category_id` SEPARATOR ',') as category_ids, URL_KEY.`store_id`
            FROM `{$this->metaData->productEntityTable}_varchar` URL_KEY
            LEFT JOIN `{$this->metaData->urlRewriteProductCategoryTable}` PG ON PG.`product_id` = URL_KEY.`entity_id`
            WHERE 
                URL_KEY.`attribute_id` = $attributeId AND
                URL_KEY.`entity_id` IN (" . implode(', ', $productIds) . ")
            GROUP BY URL_KEY.`entity_id`, URL_KEY.`store_id` 
        ");

        $data = [];
        foreach ($existingData as $existingDatum) {
            $productId = $existingDatum['product_id'];
            $storeId = $existingDatum['store_id'];
            $categoryIds = is_null($existingDatum['category_ids']) ? [] : explode(',', $existingDatum['category_ids']);
            $urlKey = $existingDatum['url_key'];
            $data[$storeId][$productId] = ['url_key' => $urlKey, 'category_ids' => $categoryIds];
        }

        return $data;
    }

    /**
     * Returns an sku => id map for all existing skus.
     *
     * @param array $skus
     * @return array
     */
    protected function getExistingSkus(array $skus)
    {
        if (count($skus) == 0) {
            return [];
        }

        $serialized = $this->db->quoteSet($skus);
        return $this->db->fetchMap("SELECT `sku`, `entity_id` FROM {$this->metaData->productEntityTable} WHERE `sku` in ({$serialized})");
    }

    /**
     * @param SimpleProduct[] $products
     */
    protected function insertMainTable(array $products)
    {
#todo has_options, required_options

        $values = [];
        $skus = [];
        foreach ($products as $product) {

            // index with sku to prevent creation of multiple products with the same sku
            // (this happens when products with different store views are inserted at once)
            if (array_key_exists($product->getSku(), $skus)) {
                continue;
            }
            $skus[$product->getSku()] = $product->getSku();

            $sku = $this->db->quote($product->getSku());
            $attributeSetId = $product->getAttributeSetId();
            $values[] = "({$attributeSetId}, 'simple', {$sku}, 0, 0)";
        }

        if (count($values) > 0) {

            $sql = "INSERT INTO `{$this->metaData->productEntityTable}` (`attribute_set_id`, `type_id`, `sku`, `has_options`, `required_options`) VALUES " .
                implode(',', $values);

            $this->db->execute($sql);

            // store the new ids with the products
            $serialized = $this->db->quoteSet($skus);
            $sql = "SELECT `sku`, `entity_id` FROM `{$this->metaData->productEntityTable}` WHERE `sku` IN ({$serialized})";
            $sku2id = $this->db->fetchMap($sql);

            foreach ($products as $product) {
                $product->id = $sku2id[$product->getSku()];
            }
        }
    }

    /**
     * @param SimpleProduct[] $products
     */
    protected function updateMainTable(array $products)
    {

        $dateTime = date('Y-m-d H:i:s');

        $attributeSetUpdates = [];
        $dateOnlyUpdates = [];
        foreach ($products as $product) {
            $attributeSetId = $product->getAttributeSetId();
            if ($attributeSetId !== null) {
                $attributeSetUpdates[] = "({$product->id}, {$attributeSetId}, '{$dateTime}')";
            } else {
                $dateOnlyUpdates[] = "({$product->id}, '{$dateTime}')";
            }
        }

        if (count($attributeSetUpdates) > 0) {

            $sql = "INSERT INTO `{$this->metaData->productEntityTable}`" .
                " (`entity_id`, `attribute_set_id`, `updated_at`) " .
                " VALUES " . implode(', ', $attributeSetUpdates) .
                " ON DUPLICATE KEY UPDATE `attribute_set_id` = VALUES(`attribute_set_id`), `updated_at`= VALUES(`updated_at`)";

            $this->db->execute($sql);
        }

        if (count($dateOnlyUpdates) > 0) {

            $sql = "INSERT INTO `{$this->metaData->productEntityTable}`" .
                " (`entity_id`, `updated_at`) " .
                " VALUES " . implode(', ', $dateOnlyUpdates) .
                " ON DUPLICATE KEY UPDATE `updated_at`= VALUES(`updated_at`)";

            $this->db->execute($sql);
        }
    }

    /**
     * @param ProductStoreView[] $storeViews
     * @param string $eavAttribute
     */
    protected function insertEavAttribute(array $storeViews, string $eavAttribute)
    {
        $attributeInfo = $this->metaData->productEavAttributeInfo[$eavAttribute];
        $tableName = $attributeInfo->tableName;
        $attributeId = $attributeInfo->attributeId;

        $values = [];
        foreach ($storeViews as $storeView) {

            $entityId = $storeView->parent->id;
            $value = $this->db->quote($storeView->getAttribute($eavAttribute));
            $storeViewId = $storeView->getStoreViewId();
            $values[] = "({$entityId},{$attributeId},{$storeViewId},{$value})";
        }

        $sql = "INSERT INTO `{$tableName}` (`entity_id`, `attribute_id`, `store_id`, `value`)" .
            " VALUES " . implode(', ', $values) .
            " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

        $this->db->execute($sql);
    }

    /**
     * @param Product[] $products
     */
    protected function insertCategoryIds(array $products)
    {
        $values = [];

        foreach ($products as $product) {
            foreach ($product->getCategoryIds() as $categoryId) {
                $values[] = "({$categoryId}, {$product->id})";
            }
        }

        if (count($values) > 0) {

            // IGNORE serves two purposes:
            // 1. do not fail if the product-category link already existed
            // 2. do not fail if the category does not exist

            $sql = "
                INSERT IGNORE INTO `{$this->metaData->categoryProductTable}` (`category_id`, `product_id`) 
                VALUES " . implode(', ', $values);

            $this->db->execute($sql);
        }
    }

    /**
     * @param Product[] $products
     */
    protected function insertWebsiteIds(array $products)
    {
        $values = [];

        foreach ($products as $product) {
            foreach ($product->getWebsiteIds() as $websiteId) {
                $values[] = "({$product->id}, {$websiteId})";
            }
        }

        if (count($values) > 0) {

            // IGNORE serves two purposes:
            // 1. do not fail if the product-website link already existed
            // 2. do not fail if the website does not exist

            $sql = "
                INSERT IGNORE INTO `{$this->metaData->productWebsiteTable}` (`product_id`, `website_id`) 
                VALUES " . implode(', ', $values);

            $this->db->execute($sql);
        }
    }

    /**
     * @param Product[] $products
     */
    protected function insertStockItems(array $products)
    {
        if (empty($products)) {
            return;
        }

        // NB: just the default stock item is inserted for now (is all Magento currently supports)
        // the code presumes 1 stock and 1 website id (0)
        $stockId = '1';
        $websiteId = '0';

        $productIds = array_column($products, 'id');

        $stockItems = $this->db->fetchMap("
            SELECT `product_id`, `item_id`
            FROM `{$this->metaData->stockItemTable}`
            WHERE `stock_id` = {$stockId} AND `website_id` = {$websiteId} AND `product_id` IN (" . implode(', ', $productIds) . ")
        ");

        foreach ($products as $product) {
            foreach ($product->getStockItems() as $stockItem) {

                $attributes =  $stockItem->getAttributes();
                if (!empty($attributes)) {

                    $attributeValues = [];

                    foreach ($attributes as $name => $value) {
                        if ($value === false) {
                            $text = '0';
                        } elseif ($value === true) {
                            $text = '1';
                        } else {
                            $text = "'{$value}'";
                        }
                        $attributeValues[] = "{$name} = {$text}";
                    }

                    if (!array_key_exists($product->id, $stockItems)) {

                        $sql = "
                            INSERT INTO `{$this->metaData->stockItemTable}`
                            SET `stock_id` = {$stockId}, `product_id` = {$product->id}, `website_id` = {$websiteId}, " . implode(',', $attributeValues) . "
                        ";

                        $this->db->execute($sql);

                    } else {

                        $itemId = $stockItems[$product->id];

                        $sql = "
                            UPDATE `{$this->metaData->stockItemTable}`
                            SET " . implode(',', $attributeValues) . "
                            WHERE `item_id` = {$itemId}
                        ";

                        $this->db->execute($sql);

                    }

                }
            }
        }
    }
}