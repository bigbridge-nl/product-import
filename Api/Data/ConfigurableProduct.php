<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class ConfigurableProduct extends Product
{
    /** @var array  */
    protected $superAttributeCodes = [];

    /** @var SimpleProduct[] */
    protected $variants = [];

    /**
     * @param string $sku
     * @param array $superAttributeCodes
     * @param array $variants
     */
    public function __construct(string $sku, array $superAttributeCodes, array $variants)
    {
        parent::__construct($sku);

        $this->superAttributeCodes = $superAttributeCodes;
        $this->variants = $variants;
    }

    public function getType()
    {
        return 'configurable';
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
     * @return SimpleProduct[]
     */
    public function getVariants()
    {
        return $this->variants;
    }

    /**
     * @return string[]
     */
    public function getSuperAttributeCodes()
    {
        return $this->superAttributeCodes;
    }
}