<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\ProductStorage;

/**
 * @author Patrick van Bergen
 */
class Utils
{
    /** @var  Magento2DbConnection */
    private $db;

    /** @var  string  */
    private $productEntityTable;

    public function __construct(Magento2DbConnection $db)
    {
        $this->db = $db;
        $this->productEntityTable = $db->getFullTableName(ProductStorage::PRODUCT_ENTITY_TABLE);
    }

    public function getProductIdBySku($quoted)
    {
        $quoted = $this->db->quote($quoted);
        return $this->db->fetchSingleCell("SELECT `entity_id` FROM " . $this->productEntityTable . " WHERE `sku` = " . $quoted);
    }
}