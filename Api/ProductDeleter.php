<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * Deletes products.
 *
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
        $this->db->deleteMultiple($this->metaData->productEntityTable, "entity_id", $ids);

        foreach ($this->metaData->getNonGlobalStoreViewIds() as $storeViewId) {
            $this->db->deleteMultipleWithWhere($this->metaData->urlRewriteTable, "entity_id", $ids, "
                `store_id` = {$storeViewId} AND `entity_type` = 'product'
            ");
        }
    }

    /**
     * @var string[] $ids
     */
    public function deleteProductsBySkus(array $skus)
    {
        if (empty($skus)) {
            return;
        }

        $ids = $this->db->fetchSingleColumn("
            SELECT `entity_id` FROM " . $this->metaData->productEntityTable . " WHERE BINARY `sku` IN (" . $this->db->getMarks($skus) . ")
        ", $skus);

        $this->deleteProductsByIds($ids);
    }
}
