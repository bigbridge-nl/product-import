<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Helper\Decimal;

/**
 * @author Patrick van Bergen
 */
class TierPrice
{
    /** @var string A 12.4 decimal */
    protected $quantity;

    /** @var string A 12.4 decimal */
    protected $value;

    /** @var string|null Null means: all customer groups */
    protected $customerGroupName;

    /** @var int */
    protected $customerGroupId = null;

    /** @var string|null Null means: all websites */
    protected $websiteCode;

    /** @var string|null */
    protected $percentageValue;

    /** @var int */
    protected $websiteId = null;

    /**
     * TierPrice constructor.
     *
     * @param string $quantity
     * @param string $value
     * @param string|null $customerGroupName The name (code) of a customer group. Null means: all customer groups
     * @param string|null $websiteCode The code of the website. Null means: all websites
     * @param string $percentageValue Since Magento 2.2
     */
    public function __construct(string $quantity, string $value, ?string $customerGroupName = null, ?string $websiteCode = null, ?string $percentageValue = null)
    {
        $this->quantity = Decimal::format($quantity);
        $this->value = Decimal::formatPrice($value);
        $this->customerGroupName = $customerGroupName;
        $this->websiteCode = $websiteCode;
        $this->percentageValue = $percentageValue !== null ? Decimal::format($percentageValue) : null;
    }

    /**
     * @return string
     */
    public function getQuantity(): string
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
     * Since Magento 2.2
     * @return string|null
     */
    public function getPercentageValue()
    {
        return $this->percentageValue;
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
