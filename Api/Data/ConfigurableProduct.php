<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class ConfigurableProduct extends Product
{
    const TYPE_CONFIGURABLE = 'configurable';

    /** @var array  */
    protected $superAttributeCodes = [];

    /** @var string[] */
    protected $variantSkus = [];

    /** @var int[] */
    protected $variantIds = [];

    /**
     * @param string $sku
     * @param array $superAttributeCodes
     * @param array $variants
     */
    public function __construct(string $sku, array $superAttributeCodes, array $variants)
    {
        parent::__construct($sku);

        $this->superAttributeCodes = $superAttributeCodes;
        $this->variantSkus = $variants;
    }

    public function getType()
    {
        return self::TYPE_CONFIGURABLE;
    }

    public function getHasOptions()
    {
        return '1';
    }

    public function getRequiredOptions()
    {
        return '1';
    }

    /**
     * @return string[]
     */
    public function getVariantSkus()
    {
        return $this->variantSkus;
    }

    /**
     * @return string[]
     */
    public function getSuperAttributeCodes()
    {
        return $this->superAttributeCodes;
    }

    /**
     * @return int[]
     */
    public function getVariantIds(): array
    {
        return $this->variantIds;
    }

    /**
     * @param int[] $variantIds
     */
    public function setVariantIds(array $variantIds)
    {
        $this->variantIds = $variantIds;
    }
}