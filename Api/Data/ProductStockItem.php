<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Helper\Decimal;

/**
 * @author Patrick van Bergen
 */
class ProductStockItem
{
    // decimal
    const QTY = 'qty';
    const MIN_QTY = 'min_qty';
    const NOTIFY_STOCK_QTY = 'notify_stock_qty';
    const MIN_SALE_QTY = 'min_sale_qty';
    const MAX_SALE_QTY = 'max_sale_qty';
    const QTY_INCREMENTS = 'qty_increments';

    // date
    const LOW_STOCK_DATE = 'low_stock_date';

    // boolean
    const USE_CONFIG_MIN_QTY = 'use_config_min_qty';
    const IS_QTY_DECIMAL = 'is_qty_decimal';
    const USE_CONFIG_BACKORDERS = 'use_config_backorders';
    const USE_CONFIG_MIN_SALE_QTY = 'use_config_min_sale_qty';
    const USE_CONFIG_MAX_SALE_QTY = 'use_config_max_sale_qty';
    const IS_IN_STOCK = 'is_in_stock';
    const USE_CONFIG_NOTIFY_STOCK_QTY = 'use_config_notify_stock_qty';
    const MANAGE_STOCK = 'manage_stock';
    const USE_CONFIG_MANAGE_STOCK = 'use_config_manage_stock';
    const STOCK_STATUS_CHANGED_AUTO = 'stock_status_changed_auto';
    const USE_CONFIG_QTY_INCREMENTS = 'use_config_qty_increments';
    const USE_CONFIG_ENABLE_QTY_INC = 'use_config_enable_qty_inc';
    const ENABLE_QTY_INCREMENTS = 'enable_qty_increments';
    const IS_DECIMAL_DIVIDED = 'is_decimal_divided';

    // int
    const BACKORDERS = 'backorders';
    const BACKORDERS_NO_BACKORDERS = 0;
    const BACKORDERS_ALLOW_QTY_BELOW_0 = 1;
    const BACKORDERS_ALLOW_QTY_BELOW_0_AND_NOTIFY_CUSTOMER = 2;

    protected $attributes = [];

    public function setQty(string $quantity)
    {
        $this->attributes[self::QTY] = Decimal::format($quantity);
    }

    public function setMinimumQuantity(string $quantity)
    {
        $this->attributes[self::MIN_QTY] = Decimal::format($quantity);
    }

    public function setNotifyStockQuantity(string $quantity)
    {
        $this->attributes[self::NOTIFY_STOCK_QTY] = Decimal::format($quantity);
    }

    public function setMinimumSaleQuantity(string $quantity)
    {
        $this->attributes[self::MIN_SALE_QTY] = Decimal::format($quantity);
    }

    public function setMaximumSaleQuantity(string $quantity)
    {
        $this->attributes[self::MAX_SALE_QTY] = Decimal::format($quantity);
    }

    public function setQuantityIncrements(string $quantityIncrements)
    {
        $this->attributes[self::QTY_INCREMENTS] = Decimal::format($quantityIncrements);
    }

    public function setLowStockDate(string $lowStockDate)
    {
        $this->attributes[self::LOW_STOCK_DATE] = trim($lowStockDate);
    }

    public function setUseConfigMinimumQuantity(bool $quantity)
    {
        $this->attributes[self::USE_CONFIG_MIN_QTY] = $quantity;
    }

    public function setIsQuantityDecimal(bool $isQuantityDecimal)
    {
        $this->attributes[self::IS_QTY_DECIMAL] = $isQuantityDecimal;
    }

    /**
     * Use BACKORDER_ constants in this class.
     *
     * @param int $backorders
     */
    public function setBackorders(int $backorders)
    {
        $this->attributes[self::BACKORDERS] = $backorders;
    }

    public function setUseConfigBackorders(bool $use)
    {
        $this->attributes[self::USE_CONFIG_BACKORDERS] = $use;
    }

    public function setUseConfigMinimumSaleQuantity(bool $use)
    {
        $this->attributes[self::USE_CONFIG_MIN_SALE_QTY] = $use;
    }

    public function setUseConfigMaximumSaleQuantity(bool $use)
    {
        $this->attributes[self::USE_CONFIG_MAX_SALE_QTY] = $use;
    }

    public function setIsInStock(bool $isInStock)
    {
        $this->attributes[self::IS_IN_STOCK] = $isInStock;
    }

    public function setUseConfigNotifyStockQuantity(bool $use)
    {
        $this->attributes[self::USE_CONFIG_NOTIFY_STOCK_QTY] = $use;
    }

    public function setManageStock(bool $manageStock)
    {
        $this->attributes[self::MANAGE_STOCK] = $manageStock;
    }

    public function setUseConfigManageStock(bool $use)
    {
        $this->attributes[self::USE_CONFIG_MANAGE_STOCK] = $use;
    }

    public function setStockStatusChangedAuto(bool $auto)
    {
        $this->attributes[self::STOCK_STATUS_CHANGED_AUTO] = $auto;
    }

    public function setUseConfigQuantityIncrements(bool $use)
    {
        $this->attributes[self::USE_CONFIG_QTY_INCREMENTS] = $use;
    }

    public function setUseConfigEnableQuantityIncrements(bool $use)
    {
        $this->attributes[self::USE_CONFIG_ENABLE_QTY_INC] = $use;
    }

    public function setEnableQuantityIncrements(bool $enable)
    {
        $this->attributes[self::ENABLE_QTY_INCREMENTS] = $enable;
    }

    public function setIsDecimalDivided(bool $isDecimalDivided)
    {
        $this->attributes[self::IS_DECIMAL_DIVIDED] = $isDecimalDivided;
    }

    public function getAttribute(string $name)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : null;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
}