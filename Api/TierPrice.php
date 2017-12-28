<?php

namespace BigBridge\ProductImport\Api;

/**
 * @author Patrick van Bergen
 */
class TierPrice
{
    /** @var int */
    protected $quantity;

    /** @var string A 12.4 price */
    protected $value;

    /** @var string */
    protected $customerGroupName;

    /** @var int */
    protected $customerGroupId = null;

    /** @var string */
    protected $websiteCode;

    /** @var int */
    protected $websiteId = null;

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
     * @return string
     */
    public function getCustomerGroupName(): string
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
     * @return string
     */
    public function getWebsiteCode(): string
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