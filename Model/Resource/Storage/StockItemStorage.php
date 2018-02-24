<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class StockItemStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * @param Product[] $products
     */
    public function storeStockItems(array $products)
    {
        if (empty($products)) {
            return;
        }

        // NB: just the default stock item is inserted for now (is all Magento currently supports)
        // the code presumes 1 stock and 1 website id (0)
        $stockId = '1';
        $websiteId = '0';

        $productIds = array_column($products, 'id');

        $stockItems = $this->db->fetchMap("
            SELECT `product_id`, `item_id`
            FROM `{$this->metaData->stockItemTable}`
            WHERE `stock_id` = ? AND `website_id` = ? AND `product_id` IN (" . $this->db->getMarks($productIds) . ")
        ", array_merge([
            $stockId,
            $websiteId
        ], $productIds));

        foreach ($products as $product) {
            foreach ($product->getStockItems() as $stockItem) {

                $attributes =  $stockItem->getAttributes();
                if (!empty($attributes)) {

                    $attributeNames = [];
                    $attributeValues = [];

                    foreach ($attributes as $name => $value) {
                        if ($value === false) {
                            $text = '0';
                        } elseif ($value === true) {
                            $text = '1';
                        } else {
                            $text = $value;
                        }
                        $attributeNames[] = "{$name} = ?";
                        $attributeValues[] = $text;
                    }

                    if (!array_key_exists($product->id, $stockItems)) {

                        $this->db->execute("
                            INSERT INTO `{$this->metaData->stockItemTable}`
                            SET `stock_id` = ?, `product_id` = ?, `website_id` = ?, " . implode(',', $attributeNames) . "
                        ", array_merge([$stockId, $product->id, $websiteId], $attributeValues));

                    } else {

                        $itemId = $stockItems[$product->id];

                        $this->db->execute("
                            UPDATE `{$this->metaData->stockItemTable}`
                            SET " . implode(',', $attributeNames) . "
                            WHERE `item_id` = ?
                        ", array_merge($attributeValues, [$itemId]));

                    }

                }
            }
        }
    }
}