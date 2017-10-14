<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;

/**
 * @author Patrick van Bergen
 */
class SimpleStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    /** @var  ImportConfig */
    protected $config;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    public function setConfig(ImportConfig $config)
    {
        $this->config = $config;
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

        $insertProducts = [];
        $updateProducts = [];
        foreach ($simpleProducts as $product) {

            if (array_key_exists($product->sku, $sku2id)) {
                $product->id = $sku2id[$product->sku];
                $updateProducts[] = $product;
            } else {
                // index with sku to prevent multiple products with the same sku
                // (this happens when products with different store views are inserted at once)
                $insertProducts[$product->sku] = $product;
            }
        }

        $this->insertProducts($insertProducts, $config->eavAttributes);
        $this->updateProducts($updateProducts, $config->eavAttributes);
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
    protected function insertProducts(array $products, $eavAttributes)
    {
        if (count($products) == 0) {
            return;
        }

        $this->insertMainTable($products);
        $this->insertEavAttributes($products, $eavAttributes);
    }

    /**
     * @param SimpleProduct[] $products
     */
    protected function updateProducts(array $products, $eavAttributes)
    {
        if (count($products) == 0) {
            return;
        }

        $this->updateMainTable($products);
        $this->insertEavAttributes($products, $eavAttributes);
    }

    protected function insertMainTable(array $products)
    {
#todo has_options, required_options

        $values = '';
        $sep = '';
        $skus = [];
        foreach ($products as $product) {
            $skus[] = $product->sku;
            $sku = $this->db->quote($product->sku);
            $attributeSetId = $this->metaData->attributeSetMap[$product->attribute_set_name];
            $values .= $sep . "({$attributeSetId}, 'simple', {$sku}, 0, 0, '{$this->db->time}', '{$this->db->time}')";
            $sep = ', ';
        }

        $sql = "INSERT INTO `{$this->metaData->productEntityTable}` (`attribute_set_id`, `type_id`, `sku`, `has_options`, `required_options`, `created_at`, `updated_at`) VALUES " . $values;
        $this->db->insert($sql);

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

        $values = '';
        $sep = '';
        $skus = [];
        foreach ($products as $product) {
            $skus[] = $product->sku;
            $sku = $this->db->quote($product->sku);
            $attributeSetId = $this->metaData->attributeSetMap[$product->attribute_set_name];
            $values .= $sep . "({$product->id},{$attributeSetId}, 'simple', {$sku}, 0, 0, '{$this->db->time}', '{$this->db->time}')";
            $sep = ', ';
        }

        $sql = "INSERT INTO `{$this->metaData->productEntityTable}` " .
            "(`entity_id`, `attribute_set_id`, `type_id`, `sku`, `has_options`, `required_options`, `created_at`, `updated_at`) " .
            "VALUES " . $values . " " .
            "ON DUPLICATE KEY UPDATE `attribute_set_id`=VALUES(`attribute_set_id`), `has_options`=VALUES(`has_options`), `required_options`=VALUES(`required_options`)," .
            "`updated_at` = '{$this->db->time}'";
        $this->db->insert($sql);
    }

    /**
     * @param SimpleProduct[] $products
     * @param string[] $eavAttributes
     */
    protected function insertEavAttributes(array $products, array $eavAttributes)
    {
        // $eavAttributes de attributen die hier gebruikt worden

        foreach ($eavAttributes as $eavAttribute) {

            $attributeInfo = $this->metaData->eavAttributeInfo[$eavAttribute];
            $tableName = $attributeInfo->tableName;
            $attributeId = $attributeInfo->attributeId;

            $values = '';
            $sep = '';
            foreach ($products as $product) {

                if (is_null($product->$eavAttribute)) {
                    continue;
                }

                $entityId = $product->id;
                $storeViewId = $this->metaData->storeViewMap[$product->store_view_code];
                $value = $this->db->quote($product->$eavAttribute);
                $values .= $sep . "({$entityId},{$attributeId},{$storeViewId},{$value})";
                $sep = ', ';
            }

            if ($values !== "") {

                $sql = "INSERT INTO `{$tableName}` (`entity_id`, `attribute_id`, `store_id`, `value`) " .
                    "VALUES " . $values .
                    "ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

                $this->db->insert($sql);
            }
        }
    }
}