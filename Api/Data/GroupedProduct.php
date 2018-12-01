<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class GroupedProduct extends Product
{
    const TYPE_GROUPED = "grouped";

    /** @var GroupedProductMember[] */
    protected $members;

    /**
     * Used in catalog_product_entity table
     * @return string
     */
    public function getType()
    {
        return self::TYPE_GROUPED;
    }

    /**
     * @param GroupedProductMember[] $members
     */
    public function setMembers(array $members)
    {
        $this->members = $members;
    }

    /**
     * @return GroupedProductMember[]
     */
    public function getMembers()
    {
        return $this->members;
    }
}