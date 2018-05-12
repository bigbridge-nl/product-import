<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class CustomOptionValue
{
    /** @var string */
    protected $sku;

    /** @var string A 12.4 price string */
    protected $price;

    /** @var string */
    protected $priceType;

    /** @var CustomOption */
    protected $customOption;

    /** @var string */
    protected $title;

    public function __construct(CustomOption $customOption, string $sku, string $price, string $priceType, string $title)
    {
        $this->customOption = $customOption;
        $this->sku = trim($sku);
        $this->price = trim($price);
        $this->priceType = trim($priceType);
        $this->title = trim($title);
    }

    /**
     * @return CustomOption
     */
    public function getCustomOption(): CustomOption
    {
        return $this->customOption;
    }

    /**
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
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