<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class BundleProductOption
{
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