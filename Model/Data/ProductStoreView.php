<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Model\GeneratedUrlKey;
use BigBridge\ProductImport\Model\Reference;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;

/**
 * @author Patrick van Bergen
 */
class ProductStoreView
{
    // a collection of some commonly used constants

    const STATUS_ENABLED = Status::STATUS_ENABLED;
    const STATUS_DISABLED = Status::STATUS_DISABLED;

    const VISIBILITY_NOT_VISIBLE = Visibility::VISIBILITY_NOT_VISIBLE;
    const VISIBILITY_IN_CATALOG = Visibility::VISIBILITY_IN_CATALOG;
    const VISIBILITY_IN_SEARCH = Visibility::VISIBILITY_IN_SEARCH;
    const VISIBILITY_BOTH = Visibility::VISIBILITY_BOTH;

    /**
     * For internal use only; not for application use
     * @var  Product
     */
    public $parent;

    /** @var  int */
    public $store_view_id;

    /** @var array  */
    public $website_ids = [];

    /** @var array  */
    protected $attributes = [];

    public function setName(string $name)
    {
        $this->attributes['name'] = trim($name);
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return array_key_exists('name', $this->attributes) ? $this->attributes['name'] : null;
    }

    public function setStatus(int $status)
    {
        $this->attributes['status'] = $status;
    }

    public function setDescription(string $description)
    {
        $this->attributes['description'] = trim($description);
    }

    public function setShortDescription(string $shortDescription)
    {
        $this->attributes['short_description'] = trim($shortDescription);
    }

    /**
     * @param string $price A 12.4 decimal field
     */
    public function setPrice(string $price)
    {
        $this->attributes['price'] = trim($price);
    }

    public function setVisibility(int $visibility)
    {
        $this->attributes['visibility'] = $visibility;
    }

    public function setTaxClassId(int $taxClassId)
    {
        $this->attributes['tax_class_id'] = $taxClassId;
    }

    public function setTaxClassName(string $taxClassName)
    {
        $this->attributes['tax_class_id'] = new Reference(trim($taxClassName));
    }

    public function setUrlKey(string $urlKey)
    {
        $this->attributes['url_key'] = trim($urlKey);
    }

    /**
     * @return string|GeneratedUrlKey|null
     */
    public function getUrlKey()
    {
        return array_key_exists('url_key', $this->attributes) ? $this->attributes['url_key'] : null;
    }

    public function generateUrlKey()
    {
        $this->attributes['url_key'] = new GeneratedUrlKey();
    }

    /**
     * @param string $price A 12.4 decimal field
     */
    public function setWeight(string $weight)
    {
        $this->attributes['weight'] = trim($weight);
    }

    /**
     * @param string $specialPrice A 12.4 decimal field
     */
    public function setSpecialPrice(string $specialPrice)
    {
        $this->attributes['special_price'] = trim($specialPrice);
    }

    /**
     * @param string $specialPriceFromDate A y-m-d MySql date
     */
    public function setSpecialFromDate(string $specialPriceFromDate)
    {
        $this->attributes['special_from_date'] = trim($specialPriceFromDate);
    }

    /**
     * @param string $specialPriceToDate A y-m-d MySql date
     */
    public function setSpecialToDate(string $specialPriceToDate)
    {
        $this->attributes['special_to_date'] = trim($specialPriceToDate);
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Removes an attribute
     */
    public function removeAttribute(string $name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * @return mixed
     */
    public function getAttribute(string $name)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : null;
    }
}