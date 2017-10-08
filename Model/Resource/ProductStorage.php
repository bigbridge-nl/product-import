<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;

/**
 * @author Patrick van Bergen
 */
class ProductStorage
{
    const ENTITY_TYPE_TABLE = 'eav_entity_type';
    const PRODUCT_ENTITY_TABLE = 'catalog_product_entity';
    const ATTRIBUTE_SET_TABLE = 'eav_attribute_set';

    /** @var  Magento2DbConnection */
    private $db;

    /** @var  string  */
    public $productEntityTable;

    /** @var array Maps attribute set name to id */
    public $attributeSetMap;

    public function __construct(Magento2DbConnection $db)
    {
        $this->db = $db;

        $this->productEntityTable = $db->getFullTableName(self::PRODUCT_ENTITY_TABLE);
        $this->attributeSetMap = $this->getProductAttributeSetMap();
    }

    /**
     * Checks $product for all known requirements.
     *
     * @param Product $product
     * @return array An array with [ok, error]
     */
    public function validate(Product $product)
    {
        $ok = true;
        $error = "";
        $sep = "";

        $sku = is_string($product->sku) ? trim($product->sku) : "";
        $name = is_string($product->name) ? trim($product->name) : "";
        $attributeSetName = is_string($product->attributeSetName) ? trim($product->attributeSetName) : "";

        if ($sku === "") {
            $ok = false;
            $error .= $sep . "missing sku";
            $sep = "; ";
        }

        if ($name === "") {
            $ok = false;
            $error .= $sep . "missing name";
            $sep = "; ";
        }

        if ($attributeSetName === "") {
            $ok = false;
            $error .= $sep . "missing attribute set name";
            $sep = "; ";
        } elseif (!isset($this->attributeSetMap[$attributeSetName])) {
            $ok = false;
            $error .= $sep . "unknown attribute set name: " . $attributeSetName;
            $sep = "; ";
        }

        return [$ok, $error];
    }

    /**
     * Returns the id of the product entity type.
     *
     * @return int
     */
    private function getProductEntityTypeId()
    {
        $entityTypeTable = $this->db->getFullTableName(self::ENTITY_TYPE_TABLE);
        $productEntityTypeId = $this->db->fetchSingleCell("SELECT `entity_type_id` FROM {$entityTypeTable} WHERE `entity_type_code` = 'catalog_product'");
        return $productEntityTypeId;
    }

    /**
     * Returns a name => id map for product attribute sets.
     *
     * @return array
     */
    protected function getProductAttributeSetMap()
    {
        $attributeSetTable = $this->db->getFullTableName(self::ATTRIBUTE_SET_TABLE);
        $entityTypeId = $this->getProductEntityTypeId();
        $map = $this->db->fetchMap("SELECT `attribute_set_name`, `attribute_set_id` FROM {$attributeSetTable} WHERE `entity_type_id` = {$entityTypeId}");
        return $map;
    }
}