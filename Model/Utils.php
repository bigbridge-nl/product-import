<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class Utils
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  string  */
    protected $productEntityTable;

    public function __construct(Magento2DbConnection $db)
    {
        $this->db = $db;
        $this->productEntityTable = $db->getFullTableName(MetaData::PRODUCT_ENTITY_TABLE);
    }

    public function getProductIdBySku($quoted)
    {
        $quoted = $this->db->quote($quoted);
        return $this->db->fetchSingleCell("SELECT `entity_id` FROM " . $this->productEntityTable . " WHERE `sku` = " . $quoted);
    }
}