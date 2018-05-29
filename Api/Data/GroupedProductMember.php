<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Helper\Decimal;

/**
 * @author Patrick van Bergen
 */
class GroupedProductMember
{
    /** @var string */
    protected $sku;

    /** @var string A 12.4 decimal number */
    protected $defaultQuantity;

    /** @var int */
    protected $productId;

    public function __construct(string $sku, string $defaultQuantity)
    {
        $this->sku = trim($sku);
        $this->defaultQuantity = Decimal::format($defaultQuantity);
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
    public function getDefaultQuantity(): string
    {
        return $this->defaultQuantity;
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