<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class TierPrice
{
    /** @var int */
    protected $quantity;

    /** @var string A 12.4 price */
    protected $value;

    /** @var string|null Null means: all customer groups */
    protected $customerGroupName;

    /** @var int */
    protected $customerGroupId = null;

    /** @var string|null Null means: all websites */
    protected $websiteCode;

    /** @var int */
    protected $websiteId = null;

    /**
     * TierPrice constructor.
     *
     * @param int $quantity
     * @param string $value
     * @param string|null $customerGroupName The name (code) of a customer group. Null means: all customer groups
     * @param string|null $websiteCode The code of the website. Null means: all websites
     */
    public function __construct(int $quantity, string $value, string $customerGroupName = null, string $websiteCode = null)
    {
        $this->quantity = $quantity;
        $this->value = $value;
        $this->customerGroupName = $customerGroupName;
        $this->websiteCode = $websiteCode;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return string|null
     */
    public function getCustomerGroupName()
    {
        return $this->customerGroupName;
    }

    /**
     * @return int|null The id of the customer group. null means all customer groups
     */
    public function getCustomerGroupId()
    {
        return $this->customerGroupId;
    }

    /**
     * @return string|null
     */
    public function getWebsiteCode()
    {
        return $this->websiteCode;
    }

    /**
     * @return int The id of the website. 0 means all websites
     */
    public function getWebsiteId(): int
    {
        return $this->websiteId;
    }

    public function setWebsiteId(int $websiteId)
    {
        $this->websiteId = $websiteId;
    }

    public function setCustomerGroupId(int $customerGroupId)
    {
        $this->customerGroupId = $customerGroupId;
    }
}