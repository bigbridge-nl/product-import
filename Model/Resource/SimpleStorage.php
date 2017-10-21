<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
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

    /** @var  IdResolver */
    protected $idResolver;

    public function __construct(Magento2DbConnection $db, MetaData $metaData, Validator $validator, IdResolver $idResolver)
    {
        $this->db = $db;
        $this->metaData = $metaData;
        $this->validator = $validator;
        $this->idResolver = $idResolver;
    }

    /**
     * @param SimpleProduct[] $simpleProducts
     * @param ImportConfig $config
     */
    public function storeSimpleProducts(array $simpleProducts, ImportConfig $config)
    {
        $this->db->execute("START TRANSACTION");

        try {

            $this->doTransaction($simpleProducts);

            $this->db->execute("COMMIT");

        } catch (PDOException $e) {

            $this->db->execute("ROLLBACK");

            foreach ($simpleProducts as $product) {
                $product->errors[] = $e->getMessage();
            }

        } catch (Exception $e) {

            $this->db->execute("ROLLBACK");

            foreach ($simpleProducts as $product) {
                $product->errors[] = $e->getTraceAsString();
            }

        }

        // call user defined functions to let them process the results
        foreach ($config->resultCallbacks as $callback) {
            foreach ($simpleProducts as $product) {
                call_user_func($callback, $product);
            }
        }
    }

    protected function doTransaction(array $simpleProducts)
    {
        // collect skus
        $skus = array_column($simpleProducts, 'sku');

        // collect inserts and updates
        $sku2id = $this->getExistingSkus($skus);

        $productsByAttribute = [];

        $insertProducts = [];
        $updateProducts = [];
        foreach ($simpleProducts as $product) {

            // replace Reference(s) with ids, changes $product->ok and $product->errors
            $this->idResolver->resolveIds($product);

            // checks all attributes, changes $product->ok and $product->errors
            $this->validator->validate($product);

            if (!$product->ok) {
                continue;
            }

            if (array_key_exists($product->sku, $sku2id)) {
                $product->id = $sku2id[$product->sku];
                $updateProducts[] = $product;
            } else {
                $insertProducts[] = $product;
            }

            foreach ($product as $key => $value) {
                if ($value !== null) {
                    $productsByAttribute[$key][] = $product;
                }
            }
        }

        if (count($insertProducts) > 0) {
            $this->insertMainTable($insertProducts);
        }
        if (count($updateProducts) > 0) {
            $this->updateMainTable($updateProducts);
        }

        foreach ($this->metaData->productEavAttributeInfo as $eavAttribute => $info) {
            if (array_key_exists($eavAttribute, $productsByAttribute)) {
                $this->insertEavAttribute($productsByAttribute[$eavAttribute], $eavAttribute);
            }
        }

        if (array_key_exists('category_ids', $productsByAttribute)) {
            $this->insertCategoryIds($productsByAttribute['category_ids']);
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
            $values []= "({$product->attribute_set_id}, 'simple', {$sku}, 0, 0)";
        }

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

        $sql = "INSERT INTO `{$this->metaData->productEntityTable}`" .
            " (`entity_id`, `attribute_set_id`, `type_id`, `sku`, `has_options`, `required_options`) " .
            " VALUES " . implode(', ', $values) .
            " ON DUPLICATE KEY UPDATE `attribute_set_id`=VALUES(`attribute_set_id`), `has_options`=VALUES(`has_options`), `required_options`=VALUES(`required_options`)";

        $this->db->execute($sql);
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
                $values []= "({$categoryId}, {$product->id})";
            }
        }

        if (!empty($values)) {

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