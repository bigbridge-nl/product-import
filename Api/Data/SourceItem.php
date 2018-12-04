<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Helper\Decimal;

/**
 * The multi-source inventory data for a single source item of a product.
 *
 * @author Patrick van Bergen
 */
class SourceItem
{
    const QTY = 'quantity';
    const IS_IN_STOCK = 'status';
    const NOTIFY_STOCK_QTY = 'notify_stock_qty';

    protected $attributes = [];

    public function setIsInStock(bool $isInStock)
    {
        $this->attributes[self::IS_IN_STOCK] = $isInStock;
    }

    public function setQty(string $quantity)
    {
        $this->attributes[self::QTY] = Decimal::format($quantity);
    }

    public function setNotifyStockQuantity(string $quantity)
    {
        $this->attributes[self::NOTIFY_STOCK_QTY] = Decimal::format($quantity);
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
}