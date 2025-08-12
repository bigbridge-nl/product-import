<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Helper\Decimal;

class Weee
{
    const DEFAULT_WEBSITE_ID = 0;
    const DEFAULT_STATE = 0;

    /** @var string */
    protected $country = null;

    /** @var string A 12.4 decimal */
    protected $value = null;

    /** @var int */
    protected $websiteId;

    /** @var int */
    protected $state;

    /**
     * @param string $country
     * @param string $value
     * @param string|null $websiteId
     * @param string|null $state
     */
    public function __construct(string $country, string $value, ?string $websiteId = null, ?string $state = null)
    {
        $this->country = $country;
        $this->value = Decimal::format($value);
        $this->websiteId = !is_null($websiteId) ? (int) $websiteId : self::DEFAULT_WEBSITE_ID;
        $this->state = !is_null($state) ? (int) $state : self::DEFAULT_STATE;
    }

    /**
     * @param string $country
     * @param string $value
     * @param string|null $websiteId
     * @param string|null $state
     * @return Weee
     */
    public static function createWeee(string $country, string $value, ?string $websiteId = null, ?string $state = null)
    {
        return new Weee($country, $value, $websiteId, $state);
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getWebsiteId(): int
    {
        return $this->websiteId;
    }

    /**
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

}
