<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class ConfigurableProduct extends Product
{
    const TYPE_CONFIGURABLE = 'configurable';

    /** @var array */
    protected $superAttributeCodes = null;

    /** @var string[] */
    protected $variantSkus = null;

    /** @var int[] */
    protected $variantIds = null;

    /**
     * @param array $variants
     */
    public function setVariantSkus(array $variants)
    {
        $this->variantSkus = $variants;
    }

    /**
     * @param array $superAttributeCodes
     */
    public function setSuperAttributeCodes(array $superAttributeCodes)
    {
        $this->superAttributeCodes = $superAttributeCodes;
    }

    public function getType()
    {
        return self::TYPE_CONFIGURABLE;
    }

    public function getHasOptions()
    {
        return true;
    }

    public function getRequiredOptions()
    {
        return true;
    }

    /**
     * @return string[]|null
     */
    public function getVariantSkus()
    {
        return $this->variantSkus;
    }

    /**
     * @return string[]|null
     */
    public function getSuperAttributeCodes()
    {
        return $this->superAttributeCodes;
    }

    /**
     * @return int[]|null
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