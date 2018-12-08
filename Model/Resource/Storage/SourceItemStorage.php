<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\SourceItem;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * Storage for multi-store inventory source items.
 *
 * @author Patrick van Bergen
 */
class SourceItemStorage
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
    public function storeSourceItems(array $products)
    {
        $quantities = [];
        $statuses = [];
        $both = [];
        $notifies = [];

        foreach ($products as $product) {

            $sku = $product->getSku();

            foreach ($product->getSourceItems() as $sourceCode => $sourceItem) {

                $attributes = $sourceItem->getAttributes();

                if (array_key_exists(SourceItem::QUANTITY, $attributes) && array_key_exists(SourceItem::STATUS, $attributes)) {
                    $both[] = $sourceCode;
                    $both[] = $sku;
                    $both[] = $attributes[SourceItem::QUANTITY];
                    $both[] = $attributes[SourceItem::STATUS];
                } else {
                    if (array_key_exists(SourceItem::QUANTITY, $attributes)) {
                        $quantities[] = $sourceCode;
                        $quantities[] = $sku;
                        $quantities[] = $attributes[SourceItem::QUANTITY];
                    }

                    if (array_key_exists(SourceItem::STATUS, $attributes)) {
                        $statuses[] = $sourceCode;
                        $statuses[] = $sku;
                        $statuses[] = $attributes[SourceItem::STATUS];
                    }
                }

                if (array_key_exists(SourceItem::NOTIFY_STOCK_QTY, $attributes)) {
                    $notifies[] = $sourceCode;
                    $notifies[] = $sku;
                    $notifies[] = $attributes[SourceItem::NOTIFY_STOCK_QTY];
                }
            }
        }

        if (!empty($both)) {
            $this->db->insertMultipleWithUpdate($this->metaData->inventorySourceItem,
                ['source_code', 'sku', 'quantity', 'status'],
                $both,
                Magento2DbConnection::_1_KB,
                "quantity = VALUES(quantity), status = VALUES(status)");
        }

        if (!empty($quantities)) {
            $this->db->insertMultipleWithUpdate($this->metaData->inventorySourceItem,
                ['source_code', 'sku', 'quantity'],
                $quantities,
                Magento2DbConnection::_1_KB,
                "quantity = VALUES(quantity)");
        }

        if (!empty($statuses)) {
            $this->db->insertMultipleWithUpdate($this->metaData->inventorySourceItem,
                ['source_code', 'sku', 'status'],
                $statuses,
                Magento2DbConnection::_1_KB,
                "status = VALUES(status)");
        }

        if (!empty($notifies)) {
            $this->db->insertMultipleWithUpdate($this->metaData->inventoryLowStockNotificationConfiguration,
                ['source_code', 'sku', 'notify_stock_qty'],
                $notifies,
                Magento2DbConnection::_1_KB,
                "notify_stock_qty = VALUES(notify_stock_qty)");
        }
    }
}