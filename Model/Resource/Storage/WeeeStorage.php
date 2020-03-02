<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

class WeeeStorage
{
    /** @var Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    /**
     * @param Magento2DbConnection $db
     * @param MetaData $metaData
     */
    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * @param Product[] $products
     */
    public function updateWeees(array $products)
    {
        $affectedProducts = [];

        foreach ($products as $product) {
            if ($product->getWeees() !== null) {
                $affectedProducts[] = $product;
            }
        }

        $this->removeWeees($affectedProducts);
        $this->insertWeees($affectedProducts);
    }

    /**
     * @param Product[] $products
     */
    public function insertWeees(array $products)
    {
        foreach ($products as $product) {

            foreach ($weees = $product->getWeees() as $i => $weee) {

                $this->db->execute("
                    INSERT INTO `{$this->metaData->weeeTable}`
                    SET
                        `website_id` = ?,
                        `entity_id` = ?,
                        `country` = ?,
                        `value` = ?,
                        `state` = ?,
                        `attribute_id` = ?
                ", [
                    (int)$weee->getWebsiteId(),
                    $product->id,
                    $weee->getCountry(),
                    $weee->getValue(),
                    (int)$weee->getState(),
                    $this->metaData->weeeAttributeId
                ]);
            }
        }
    }

    /**
     * @param Product[] $products
     */
    protected function removeWeees(array $products)
    {
        $productIds = array_column($products, 'id');

        $this->db->deleteMultiple($this->metaData->weeeTable, 'entity_id', $productIds);
    }

}
