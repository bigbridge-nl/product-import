<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Helper\Decimal;

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

    /** @var int */
    protected $productId;

    /**
     * @param string $sku
     * @param bool $isDefault Is this product selected by default from this option
     * @param int $priceType Fixed or percent. Use a PRICE_TYPE constant from this class
     * @param string $priceValue Price, 12.4 decimal Either a fixed price or a percentage
     * @param string $quantity Default quantity
     * @param bool $canChangeQuantity Is the customer enabled to change the quantity?
     */
    public function __construct(string $sku, bool $isDefault, int $priceType, string $priceValue, string $quantity, bool $canChangeQuantity)
    {
        $this->sku = trim($sku);
        $this->isDefault = $isDefault;
        $this->priceType = $priceType;
        $this->priceValue = Decimal::formatPrice($priceValue);
        $this->quantity = Decimal::format($quantity);
        $this->canChangeQuantity = $canChangeQuantity;
    }

    /**
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * @return int
     */
    public function getPriceType(): int
    {
        return $this->priceType;
    }

    /**
     * @return string
     */
    public function getPriceValue(): string
    {
        return $this->priceValue;
    }

    /**
     * @return string
     */
    public function getQuantity(): string
    {
        return $this->quantity;
    }

    /**
     * @return bool
     */
    public function isCanChangeQuantity(): bool
    {
        return $this->canChangeQuantity;
    }

    /**
     * @return int
     */
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @param int $productId
     */
    public function setProductId(int $productId)
    {
        $this->productId = $productId;
    }
}
