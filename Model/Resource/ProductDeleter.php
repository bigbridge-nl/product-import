<?php

namespace BigBridge\Model\Resource;

use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class ProductDeleter
{
    /** @var MetaData */
    protected $metaData;

    /** @var Magento2DbConnection */
    protected $db;

    public function __construct(
        MetaData $metaData,
        Magento2DbConnection $db
    )
    {
        $this->metaData = $metaData;
        $this->db = $db;
    }

    /**
     * @var string[] $ids
     */
    public function deleteProductsByIds(array $ids)
    {
        $this->db->deleteMultiple($this->metaData->productEntityTable, "id", $ids);
    }

    /**
     * @var string[] $ids
     */
    public function deleteProductsBySkus(array $skus)
    {
        $this->db->deleteMultiple($this->metaData->productEntityTable, "sku", $skus);
    }
}