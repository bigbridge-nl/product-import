<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Helper\Decimal;

/**
 * @author Patrick van Bergen
 */
class CustomOptionValue
{
    /** @var string A 12.4 price string */
    protected $price;

    /** @var string */
    protected $priceType;

    /** @var string */
    protected $title;

    /** @var string */
    protected $unit;

    /** @var string */
    protected $price_unit_qty;

    public function __construct(string $price, string $priceType, string $title, string $unit, string $priceUnitQty)
    {
        $this->price = Decimal::formatPrice($price);
        $this->priceType = trim($priceType);
        $this->title = trim($title);
        $this->unit = trim($unit);
        $this->price_unit_qty = trim($priceUnitQty);
    }

    /**
     * @return string
     */
    public function getPrice(): string
    {
        return $this->price;
    }

    /**
     * @return string
     */
    public function getPriceType(): string
    {
        return $this->priceType;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * @return string
     */
    public function getPriceUnitQty(): string
    {
        return $this->price_unit_qty;
    }
}
