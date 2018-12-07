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
    const QUANTITY = 'quantity';
    const STATUS = 'status';
    const NOTIFY_STOCK_QTY = 'notify_stock_qty';

    protected $attributes = [];

    /**
     * @param int $status 0: not in stock, 1: in stock
     */
    public function setStatus(int $status)
    {
        $this->attributes[self::STATUS] = $status;
    }

    public function setQuantity(string $quantity)
    {
        $this->attributes[self::QUANTITY] = Decimal::format($quantity);
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