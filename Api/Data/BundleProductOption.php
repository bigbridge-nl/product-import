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
     * @param BundleProductSelection[] $productSelections
     */
    public function setProductSelections(array $productSelections)
    {
        $this->selections = $productSelections;
    }

    /**
     * @return BundleProductSelection[]
     */
    public function getSelections(): array
    {
        return $this->selections;
    }
}