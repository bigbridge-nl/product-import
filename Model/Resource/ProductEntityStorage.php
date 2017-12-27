<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;

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

        $serialized = $this->db->quoteSet($skus);
        return $this->db->fetchMap("SELECT `sku`, `entity_id` FROM {$this->metaData->productEntityTable} WHERE `sku` in ({$serialized})");
    }

    /**
     * @param Product[] $products
     * @param string $type
     * @param bool $hasOptions
     * @param bool $requiredOptions
     */
    public function insertMainTable(array $products, string $type, int $hasOptions, int $requiredOptions)
    {
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
            $values[] = "({$attributeSetId}, '{$type}', {$sku}, {$hasOptions}, {$requiredOptions})";
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
     * @param Product[] $products
     */
    public function updateMainTable(array $products)
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
}