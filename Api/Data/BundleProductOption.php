<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class BundleProductOption
{
    const PRICE_TYPE_FIXED = 0;
    const PRICE_TYPE_PERCENT = 1;

    /** @var string */
    protected $inputType;

    /** @var bool */
    protected $required;

    /** @var BundleProductSelection[] */
    protected $selections = [];

    /** @var int */
    public $id;

    public function __construct(string $inputType, bool $required)
    {
        $this->inputType = $inputType;
        $this->required = $required;
    }

    /**
     * @return string
     */
    public function getInputType(): string
    {
        return $this->inputType;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @param string $sku
     * @param bool $isDefault Is this product selected by default from this option
     * @param int $priceType Fixed or percent. Use a PRICE_TYPE constant from this class
     * @param string $priceValue Price, 12.4 decimal Either a fixed price or a percentage
     * @param string $quantity Default quantity
     * @param bool $canChangeQuantity Is the customer enabled to change the quantity?
     */
    public function addProductSelection(string $sku, bool $isDefault, int $priceType, string $priceValue, string $quantity, bool $canChangeQuantity)
    {
        $this->selections[] = new BundleProductSelection($sku, $isDefault, $priceType, $priceValue, $quantity, $canChangeQuantity);
    }

    /**
     * @return BundleProductSelection[]
     */
    public function getSelections(): array
    {
        return $this->selections;
    }
}