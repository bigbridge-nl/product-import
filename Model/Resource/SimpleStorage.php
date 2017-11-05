<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Reference\UrlKeyGenerator;
use Exception;
use PDOException;

/**
 * @author Patrick van Bergen
 */
class SimpleStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    /** @var Validator  */
    protected $validator;

    /** @var  ReferenceResolver */
    protected $referenceResolver;

    /** @var UrlKeyGenerator */
    protected $urlKeyGenerator;

    public function __construct(Magento2DbConnection $db, MetaData $metaData, Validator $validator, ReferenceResolver $referenceResolver, UrlKeyGenerator $urlKeyGenerator)
    {
        $this->db = $db;
        $this->metaData = $metaData;
        $this->validator = $validator;
        $this->referenceResolver = $referenceResolver;
        $this->urlKeyGenerator = $urlKeyGenerator;
    }

    /**
     * @param SimpleProduct[] $simpleProducts
     * @param ImportConfig $config
     */
    public function storeSimpleProducts(array $simpleProducts, ImportConfig $config)
    {
        // collect skus
        $skus = array_column($simpleProducts, 'sku');

        // collect inserts and updates
        $sku2id = $this->getExistingSkus($skus);

        $insertProducts = $updateProducts = [];

        // separate new products from existing products and assign id
        foreach ($simpleProducts as $product) {
            if (array_key_exists($product->sku, $sku2id)) {
                $product->id = $sku2id[$product->sku];
                $updateProducts[] = $product;
            } else {
                $insertProducts[] = $product;
            }
        }

        // create url keys based on name and id
        // changes $product->ok and $product->errors
        $this->urlKeyGenerator->createUrlKeysForNewProducts($insertProducts, $config->urlKeyScheme, $config->duplicateUrlKeyStrategy);

        $this->urlKeyGenerator->createUrlKeysForExistingProducts($updateProducts, $config->urlKeyScheme, $config->duplicateUrlKeyStrategy);

        $validProducts = [];

        foreach ($simpleProducts as $product) {

            // replace Reference(s) with ids, changes $product->ok and $product->errors
            $this->referenceResolver->resolveIds($product, $config);

            // checks all attributes, changes $product->ok and $product->errors
            $this->validator->validate($product);

            if (!$product->ok) {
                continue;
            }

            // collect valid products
            $validProducts[] = $product;
        }

        // in a "dry run" no actual imports to the database are done
        if (!$config->dryRun) {

            $this->saveProducts($validProducts);
        }

        // call user defined functions to let them process the results
        foreach ($config->resultCallbacks as $callback) {
            foreach ($simpleProducts as $product) {
                call_user_func($callback, $product);
            }
        }
    }

    protected function saveProducts(array $validProducts)
    {
        $validUpdateProducts = $validInsertProducts = [];
        $productsByAttribute = [];

        foreach ($validProducts as $product) {

            // collect valid new and existing products
            if ($product->id !== null) {
                $validUpdateProducts[] = $product;
            } else {
                $validInsertProducts[] = $product;
            }

            // collect products by attribute
            foreach ($product as $key => $value) {
                if ($value !== null) {
                    $productsByAttribute[$key][] = $product;
                }
            }
        }

        $this->db->execute("START TRANSACTION");

        try {
            $this->insertMainTable($validInsertProducts);
            $this->updateMainTable($validUpdateProducts);

            foreach ($this->metaData->productEavAttributeInfo as $eavAttribute => $info) {
                if (array_key_exists($eavAttribute, $productsByAttribute)) {
                    $this->insertEavAttribute($productsByAttribute[$eavAttribute], $eavAttribute);
                }
            }

            if (array_key_exists('category_ids', $productsByAttribute)) {
                $this->insertCategoryIds($productsByAttribute['category_ids']);
            }

            // url_rewrite (must be done after url_key and category_id)
            $this->insertRewrites($validInsertProducts);
            $this->updateRewrites($validUpdateProducts);

            $this->db->execute("COMMIT");

        } catch (PDOException $e) {

            try { $this->db->execute("ROLLBACK"); } catch(Exception $f) {}

            foreach ($validProducts as $product) {
                $product->errors[] = $e->getMessage();
                $product->ok = false;
            }

        } catch (Exception $e) {

            try { $this->db->execute("ROLLBACK"); } catch(Exception $f) {}

            foreach ($validProducts as $product) {
                $message = $e->getMessage();
                $product->errors[] = $message;
                $product->ok = false;
            }

        }
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
            if (array_key_exists($product->sku, $skus)) {
                continue;
            }
            $skus[$product->sku] = $product->sku;

            $sku = $this->db->quote($product->sku);
            $values[] = "({$product->attribute_set_id}, 'simple', {$sku}, 0, 0)";
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
                $product->id = $sku2id[$product->sku];
            }
        }
    }

    /**
     * @param SimpleProduct[] $products
     */
    protected function updateMainTable(array $products)
    {

#todo has_options, required_options

        $values = [];
        $skus = [];
        foreach ($products as $product) {
            $skus[] = $product->sku;
            $sku = $this->db->quote($product->sku);
            $values[] = "({$product->id},{$product->attribute_set_id}, 'simple', {$sku}, 0, 0)";
        }

        if (count($values) > 0) {

            $sql = "INSERT INTO `{$this->metaData->productEntityTable}`" .
                " (`entity_id`, `attribute_set_id`, `type_id`, `sku`, `has_options`, `required_options`) " .
                " VALUES " . implode(', ', $values) .
                " ON DUPLICATE KEY UPDATE `attribute_set_id`=VALUES(`attribute_set_id`), `has_options`=VALUES(`has_options`), `required_options`=VALUES(`required_options`)";

            $this->db->execute($sql);
        }
    }

    /**
     * @param SimpleProduct[] $products
     */
    protected function insertRewrites(array $products)
    {
        if (empty($products)) {
            return;
        }

        // all store view ids, without 0
        $allStoreIds = array_diff($this->metaData->storeViewMap, ['0']);

        $productIds = array_column($products, 'id');
        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        $results = $this->db->fetchAllAssoc("
            SELECT `entity_id`, `store_id`, `value` AS `url_key`
            FROM `{$this->metaData->productEntityTable}_varchar`
            WHERE
                `attribute_id` = {$attributeId} AND
                `entity_id` IN (" . $this->db->quoteSet($productIds) . ")
        ");

        $urlKeys = [];
        foreach ($results as $result) {
            $productId = $result['entity_id'];
            $storeId = $result['store_id'];
            $urlKey = $result['url_key'];

            if ($storeId == 0) {
                // insert url key to all store views
                foreach ($allStoreIds as $aStoreId) {
                    // but do not overwrite explicit assignments
                    if (!array_key_exists($productId, $urlKeys) || !array_key_exists($urlKeys[$productId], $aStoreId)) {
                        $urlKeys[$productId][$aStoreId] = $urlKey;
                    }
                }
            } else {
                $urlKeys[$productId][$storeId] = $urlKey;
            }
        }

        // category ids per product
        $categoryIds = [];
        $results = $this->db->fetchAllAssoc("
            SELECT `product_id`, `category_id`
            FROM `{$this->metaData->categoryProductTable}`
            WHERE
                `product_id` IN (" . $this->db->quoteSet($productIds) .")
        ");

        $values = [];
        foreach ($results as $result) {
            $categoryIds[$result['product_id']][$result['category_id']] = $result['category_id'];
        }

        foreach ($urlKeys as $productId => $urlKeyData) {
            foreach ($urlKeyData as $storeId => $urlKey) {

                $shortUrl = $urlKey . $this->metaData->productUrlSuffix;

                // url keys without categories
                $requestPath = $this->db->quote($shortUrl);
                $targetPath = $this->db->quote('catalog/product/view/id/' . $productId);
                $values[] = "('product', {$productId},{$requestPath}, {$targetPath}, 0, {$storeId}, 1, null)";

                // url keys with categories
                foreach ($categoryIds[$productId] as $directCategoryId) {

                    // here we check if the category id supplied actually exists
                    if (!array_key_exists($directCategoryId, $this->metaData->allCategoryInfo)) {
                        continue;
                    }

                    $path = "";
                    foreach ($this->metaData->allCategoryInfo[$directCategoryId]->path as $i => $parentCategoryId) {

                        // the root category is not used for the url path
                        if ($i === 0) {
                            continue;
                        }

                        $categoryInfo = $this->metaData->allCategoryInfo[$parentCategoryId];

                        // take the url_key from the store view, or default to the global url_key
                        $urlKey = array_key_exists($storeId, $categoryInfo->urlKeys) ? $categoryInfo->urlKeys[$storeId] : $categoryInfo->urlKeys[0];

                        $path .= $urlKey . "/";

                        $requestPath = $this->db->quote($path . $shortUrl);
                        $targetPath = $this->db->quote('catalog/product/view/id/' . $productId . '/category/' . $parentCategoryId);
                        $metadata = $this->db->quote(serialize(['category_id' => (string)$parentCategoryId]));
                        $values[] = "('product', {$productId},{$requestPath}, {$targetPath}, 0, {$storeId}, 1, {$metadata})";
                    }
                }
            }
        }

        if (count($values) > 0) {

            // IGNORE works on the key request_path, store_id
            // when this combination already exists, it is ignored
            // this may happen if a main product is followed by one of its store views
            $sql = "
            INSERT IGNORE INTO `{$this->metaData->urlRewriteTable}`
            (`entity_type`, `entity_id`, `request_path`, `target_path`, `redirect_type`, `store_id`, `is_autogenerated`, `metadata`)
            VALUES " . implode(', ', $values) . "
        ";

            $this->db->execute($sql);
        }
    }

    protected function updateRewrites(array $products)
    {

    }

    /**
     * @param SimpleProduct[] $products
     * @param string $eavAttribute
     */
    protected function insertEavAttribute(array $products, string $eavAttribute)
    {
        $attributeInfo = $this->metaData->productEavAttributeInfo[$eavAttribute];
        $tableName = $attributeInfo->tableName;
        $attributeId = $attributeInfo->attributeId;

        $values = [];
        foreach ($products as $product) {

            $entityId = $product->id;
            $value = $this->db->quote($product->$eavAttribute);
            $values[] = "({$entityId},{$attributeId},{$product->store_view_id},{$value})";
        }

        $sql = "INSERT INTO `{$tableName}` (`entity_id`, `attribute_id`, `store_id`, `value`)" .
            " VALUES " . implode(', ', $values) .
            " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

        $this->db->execute($sql);
    }

    protected function insertCategoryIds(array $products)
    {
        $values = [];

        foreach ($products as $product) {
            foreach ($product->category_ids as $categoryId) {
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
}