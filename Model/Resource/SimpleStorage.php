<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Data\SimpleProduct;


// https://dev.mysql.com/doc/refman/5.6/en/optimizing-innodb-bulk-data-loading.html


/**
 * @author Patrick van Bergen
 */
class SimpleStorage
{
    const ATT_SKU = 'sku';

    /** @var  Magento2DbConnection */
    private $db;

    /** @var  ProductStorage */
    private $shared;

    public function __construct(Magento2DbConnection $db, ProductStorage $shared)
    {
        $this->db = $db;
        $this->shared = $shared;
    }

    /**
     * @param SimpleProduct[] $simpleProducts
     */
    public function storeSimpleProducts(array $simpleProducts)
    {
        if (empty($simpleProducts)) {
            return;
        }

        // collect skus
        $skus = array_column($simpleProducts, self::ATT_SKU);

        // collect inserts and updates
        $updateSkus = $this->getExistingSkus($skus);
        $insertSkus = array_diff($skus, $updateSkus);

        $newProducts = $simpleProducts;

        // main table attributes

        // for each attribute

            // store all values in one insert
        $this->insertProducts($newProducts);

            // store all values in one update

        // update flat table
    }

    private function getExistingSkus(array $skus)
    {
        // SELECT `entity_id` FROM catalog_product_entity WHERE `sku` in ()
        return [];
    }

    /**
     * @param SimpleProduct[] $products
     */
    private function insertProducts(array $products)
    {
        static $columns = [
            'attribute_set_id',
            'type_id',
            'sku',
            'has_options',
            'required_options',
            'created_at',
            'updated_at',
        ];

        $values = '';
        $sep = '';
        foreach ($products as $product) {
            $sku = $this->db->quote($product->sku);
            $attributeSetId = $this->shared->attributeSetMap[$product->attributeSetName] ?: null;
            $values .= $sep . "({$attributeSetId}, 'simple', {$sku}, 0, 0, '{$this->db->time}', '{$this->db->time}')";
            $sep = ', ';
        }

        $sql = "INSERT INTO `{$this->shared->productEntityTable}` (" . implode(",", $columns) . ") VALUES " . $values;

        $this->db->insert($sql);
    }
}