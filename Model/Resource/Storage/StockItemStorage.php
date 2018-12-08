<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
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
        // NB: just the default stock item is inserted for now (is all Magento currently supports)
        // the code presumes 1 stock and 1 website id (0)
        $stockId = '1';
        $websiteId = '0';

        // collect values by attributes
        $values = [];
        foreach ($products as $product) {
            foreach ($product->getStockItems() as $stockItem) {
                foreach ($stockItem->getAttributes() as $attributeCode => $attributeValue) {
                    $values[$attributeCode][$product->id] = $attributeValue;
                }
            }
        }

        foreach ($values as $attributeCode => $attributeValues) {

            $values = [];
            foreach ($attributeValues as $productId => $attributeValue) {

                if ($attributeValue === false) {
                    $text = '0';
                } elseif ($attributeValue === true) {
                    $text = '1';
                } else {
                    $text = $attributeValue;
                }

                $values[] = $stockId;
                $values[] = $websiteId;
                $values[] = $productId;
                $values[] = $text;
            }

            $this->db->insertMultipleWithUpdate(
                $this->metaData->stockItemTable,
                ['stock_id', 'website_id', 'product_id', $attributeCode],
                $values,
                Magento2DbConnection::_1_KB,
                "{$attributeCode} = VALUES({$attributeCode})"
            );
        }
    }
}