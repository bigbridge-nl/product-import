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

    public function __construct(string $price, string $priceType, string $title)
    {
        $this->price = Decimal::formatPrice($price);
        $this->priceType = trim($priceType);
        $this->title = trim($title);
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
}
