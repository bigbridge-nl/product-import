<?php

namespace BigBridge\ProductImport\Api;

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