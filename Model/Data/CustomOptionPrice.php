<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Api\Data\CustomOption;

/**
 * @author Patrick van Bergen
 */
class CustomOptionPrice
{
    /** @var CustomOption */
    protected $customOption;

    /** @var string */
    protected $price;

    /** @var string */
    protected $priceType;

    public function __construct(CustomOption $customOption, string $price, string $priceType)
    {
        $this->customOption = $customOption;
        $this->price = trim($price);
        $this->priceType = trim($priceType);
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
     * @return CustomOption
     */
    public function getCustomOption(): CustomOption
    {
        return $this->customOption;
    }

}