<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Data\SimpleProduct;

// https://dev.mysql.com/doc/refman/5.6/en/insert-optimization.html
// https://dev.mysql.com/doc/refman/5.6/en/optimizing-innodb-bulk-data-loading.html

/**
 * @author Patrick van Bergen
 */
class SimpleStorage
{
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
     * Checks $product for all known requirements.
     *
     * @param SimpleProduct $product
     * @return array An array with [ok, error]
     */
    public function validate(SimpleProduct $product)
    {
        $ok = true;
        $error = "";

        if ($product->sku === null) {
            $ok = false;
            $error = "Missing SKU";
        }

        return [$ok, $error];
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
        $skus = array_column($simpleProducts, 'sku');

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
        return $this->db->fetchMap("SELECT `sku`, `entity_id` FROM {$this->shared->productEntityTable} WHERE `sku` in ({$serialized})");
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

#todo has_options, required_options

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