<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class BundleProductSelection
{
    /** @var string */
    protected $sku;
    
    /** @var bool */
    protected $isDefault;

    /** @var int */
    protected $priceType;

    /** @var string */
    protected $priceValue;

    /** @var string */
    protected $quantity;

    /** @var bool */
    protected $canChangeQuantity;

    public function __construct(string $sku, bool $isDefault, int $priceType, string $priceValue, string $quantity, bool $canChangeQuantity)
    {
        $this->sku = $sku;
        $this->isDefault = $isDefault;
        $this->priceType = $priceType;
        $this->priceValue = $priceValue;
        $this->quantity = $quantity;
        $this->canChangeQuantity = $canChangeQuantity;
    }
}