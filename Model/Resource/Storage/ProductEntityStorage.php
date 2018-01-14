<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
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

        $serialized = $this->db->quoteSet($skus);
        return $this->db->fetchMap("SELECT `sku`, `entity_id` FROM {$this->metaData->productEntityTable} WHERE `sku` in ({$serialized})");
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
            WHERE `entity_id` IN (" . implode(', ', $productIds) . ")
        ");

        foreach ($products as $product) {
            if (!array_key_exists($product->id, $exists)) {
                $product->addError("Id does not belong to existing product: " . $product->id);
            }
        }
    }

    /**
     * @param Product[] $products
     * @param string $type
     * @param bool $hasOptions
     * @param bool $requiredOptions
     */
    public function insertMainTable(array $products)
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
            $type = $product->getType();
            $hasOptions = $product->getHasOptions();
            $requiredOptions = $product->getRequiredOptions();
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
        $otherUpdates = [];
        foreach ($products as $product) {
            $sku = $product->getSku();
            $attributeSetId = $product->getAttributeSetId();
            if ($attributeSetId !== null) {
                $attributeSetUpdates[] = "({$product->id}, '{$sku}', {$attributeSetId}, '{$dateTime}')";
            } else {
                $otherUpdates[] = "({$product->id}, '{$sku}', '{$dateTime}')";
            }
        }

        if (count($attributeSetUpdates) > 0) {

            $sql = "INSERT INTO `{$this->metaData->productEntityTable}`" .
                " (`entity_id`, `sku`, `attribute_set_id`, `updated_at`) " .
                " VALUES " . implode(', ', $attributeSetUpdates) .
                " ON DUPLICATE KEY UPDATE `sku` = VALUES(`sku`), `attribute_set_id` = VALUES(`attribute_set_id`), `updated_at`= VALUES(`updated_at`)";

            $this->db->execute($sql);
        }

        if (count($otherUpdates) > 0) {

            $sql = "INSERT INTO `{$this->metaData->productEntityTable}`" .
                " (`entity_id`, `sku`, `updated_at`) " .
                " VALUES " . implode(', ', $otherUpdates) .
                " ON DUPLICATE KEY UPDATE `sku` = VALUES(`sku`), `updated_at`= VALUES(`updated_at`)";

            $this->db->execute($sql);
        }
    }
}