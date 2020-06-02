<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * Provides a view to some of the importer's internal data to external classes that should not have direct access.
 *
 * @author Patrick van Bergen
 */
class Information
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
     * Returns the codes of all store views, except for the global store view.
     * @return string[]
     */
    public function getNonGlobalStoreViewCodes()
    {
        return $this->metaData->getNonGlobalStoreViewCodes();
    }

    /**
     * Returns all product ids
     *
     * @return array
     */
    public function getProductIds()
    {
        return $this->db->fetchSingleColumn("
            SELECT `entity_id`
            FROM `" . $this->metaData->productEntityTable . "`
        ");
    }

    /**
     * Given an SKU with incorrect case, returns the SKU with the right case.
     *
     * @param string $caseInsensitiveSku
     */
    public function getCaseSensitiveSku(string $caseInsensitiveSku)
    {
        $sku = $this->db->fetchSingleCell("
            SELECT `sku`
            FROM {$this->metaData->productEntityTable}
            WHERE `sku` = ?
        ", [
            $caseInsensitiveSku
        ]);

        if (!$sku) {
            return false;
        } else {
            return $sku;
        }
    }
}
