<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class GroupedProduct extends Product
{
    /** @var GroupedProductMember[]  */
    protected $members;

    /**
     * GroupedProduct constructor.
     * @param string $sku
     * @param GroupedProductMember[] $members
     */
    public function __construct(string $sku, array $members)
    {
        parent::__construct($sku);

        $this->members = $members;
    }

    /**
     * Used in catalog_product_entity table
     * @return string
     */
    public function getType()
    {
        return "grouped";
    }

    /**
     * @return GroupedProductMember[]
     */
    public function getMembers(): array
    {
        return $this->members;
    }
}