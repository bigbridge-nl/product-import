<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class BundleProductOption
{
    /** @var int */
    protected $inputType;

    /** @var bool */
    protected $required;

    /** @var BundleProductSelection[] */
    protected $selections = [];

    public function __construct(int $inputType, bool $required)
    {
        $this->inputType = $inputType;
        $this->required = $required;
    }

    /**
     * @return int
     */
    public function getInputType(): int
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

    public function addProductSelection(string $sku, bool $isDefault, int $priceType, string $priceValue, string $quantity, bool $canChangeQuantity)
    {
        $this->selections[] = new BundleProductSelection($sku, $isDefault, $priceType, $priceValue, $quantity, $canChangeQuantity);
    }
}