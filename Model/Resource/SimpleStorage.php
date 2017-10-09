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
    private $db;

    /** @var  MetaData */
    private $metaData;

    /** @var  ImportConfig */
    private $config;

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
        if (empty($simpleProducts)) {
            return;
        }

        // collect skus
        $skus = array_column($simpleProducts, 'sku');

        // collect inserts and updates
        $updateSkus = $this->getExistingSkus($skus);
        $insertSkus = array_diff($skus, $updateSkus);

        $newProducts = $simpleProducts;

        // main table attributes

        // for each attribute

            // store all values in one insert
        $this->insertProducts($newProducts, $config->eavAttributes);

            // store all values in one update

        // update flat table
    }

    /**
     * Returns an sku => id map for all existing skus.
     *
     * @param array $skus
     * @return array
     */
    private function getExistingSkus(array $skus)
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
    private function insertProducts(array $products, $eavAttributes)
    {

        if (count($products) == 0) {
            return;
        }

        $this->insertMainTable($products);
        $this->insertEavAttributes($products, $eavAttributes);
    }

    private function insertMainTable(array $products)
    {
#todo has_options, required_options

        $values = '';
        $sep = '';
        $skus = [];
        foreach ($products as $product) {
            $skus[] = $product->sku;
            $sku = $this->db->quote($product->sku);
            $attributeSetId = $this->metaData->attributeSetMap[$product->attributeSetName] ?: null;
            $values .= $sep . "({$attributeSetId}, 'simple', {$sku}, 0, 0, '{$this->db->time}', '{$this->db->time}')";
            $sep = ', ';
        }

        $sql = "INSERT INTO `{$this->metaData->productEntityTable}` (`attribute_set_id`,`type_id`,`sku`,`has_options`,`required_options`,`created_at`,`updated_at`) VALUES " . $values;
        $this->db->insert($sql);

        // store the new ids with the products
        $serialized = $this->db->quoteSet($skus);
        $sql = "SELECT `sku`, `entity_id` FROM `{$this->metaData->productEntityTable}` WHERE `sku` IN ({$serialized})";
        $sku2id = $this->db->fetchMap($sql);

        foreach ($products as $product) {
            $product->id = $sku2id[$product->sku];
        }
    }

    private function insertEavAttributes(array $products, array $eavAttributes)
    {
        // $eavAttributes de attributen die hier gebruikt worden

#todo
        $storeId = 0;

        foreach ($eavAttributes as $eavAttribute) {

            $attributeInfo = $this->metaData->attributeInfo[$eavAttribute];
            $tableName = $attributeInfo->tableName;
            $attributeId = $attributeInfo->attributeId;

            $values = '';
            $sep = '';
            foreach ($products as $product) {
                $entityId = $product->id;
                $value = $this->db->quote($product->$eavAttribute);
                $values .= $sep . "({$attributeId},{$storeId},{$entityId},{$value})";
                $sep = ', ';
            }

            $sql = "INSERT INTO `{$tableName}` (`attribute_id`,`store_id`,`entity_id`,`value`) VALUES " . $values;

            $this->db->insert($sql);
        }
    }
}