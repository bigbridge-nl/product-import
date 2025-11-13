<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Helper\Decimal;
use BigBridge\ProductImport\Model\Data\CustomOptionPrice;
use BigBridge\ProductImport\Model\Data\CustomOptionTitle;
use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Data\ImageGalleryInformation;
use BigBridge\ProductImport\Model\Data\GeneratedUrlKey;
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

    const NOT_AVAILABLE = 0;
    const AVAILABLE = 1;

    const MSRP_USE_CONFIG = 0;
    const MSRP_ON_GESTURE = 1;
    const MSRP_IN_CART = 2;
    const MSRP_BEFORE_ORDER_CONFIRMATION = 3;

    const VISIBILITY_NOT_VISIBLE = Visibility::VISIBILITY_NOT_VISIBLE;
    const VISIBILITY_IN_CATALOG = Visibility::VISIBILITY_IN_CATALOG;
    const VISIBILITY_IN_SEARCH = Visibility::VISIBILITY_IN_SEARCH;
    const VISIBILITY_BOTH = Visibility::VISIBILITY_BOTH;

    const ATTR_VISIBILITY = 'visibility';
    const ATTR_URL_KEY = 'url_key';
    const ATTR_URL_PATH = 'url_path';
    const ATTR_TAX_CLASS_ID = 'tax_class_id';
    const ATTR_PRICE = 'price';
    const ATTR_COST = 'cost';
    const ATTR_STATUS = 'status';
    const ATTR_DESCRIPTION = 'description';
    const ATTR_SHORT_DESCRIPTION = 'short_description';
    const ATTR_NAME = 'name';
    const ATTR_WEIGHT = 'weight';
    const ATTR_SPECIAL_PRICE = 'special_price';
    const ATTR_SPECIAL_FROM_DATE = 'special_from_date';
    const ATTR_SPECIAL_TO_DATE = 'special_to_date';
    const ATTR_META_TITLE = 'meta_title';
    const ATTR_META_DESCRIPTION = 'meta_description';
    const ATTR_META_KEYWORDS = 'meta_keyword';
    const ATTR_NEWS_FROM_DATE = 'news_from_date';
    const ATTR_NEWS_TO_DATE = 'news_to_date';
    const ATTR_GIFT_MESSAGE_AVAILABLE = 'gift_message_available';
    const ATTR_COUNTRY_OF_MANUFACTURE = 'country_of_manufacture';
    const ATTR_MSRP = 'msrp';
    const ATTR_MSRP_DISPLAY_ACTUAL_PRICE_TYPE = 'msrp_display_actual_price_type';
    const ATTR_COLOR = 'color';
    const ATTR_MANUFACTURER = 'manufacturer';

    const SWATCH_IMAGE = 'swatch_image';
    const SMALL_IMAGE = 'small_image';
    const BASE_IMAGE = 'image';
    const THUMBNAIL_IMAGE = 'thumbnail';

    const PRICE_TYPE_FIXED = 'fixed';
    const PRICE_TYPE_PERCENT = 'percent';

    /**
     * For internal use only; not for application use
     * @var  Product
     */
    public $parent;

    /** @var  int|null */
    protected $store_view_id;

    /** @var ImageGalleryInformation[] */
    protected $imageGalleryInformation = [];

    /** @var array */
    protected $imageRoles = [];

    /** @var array */
    protected $attributes = [];

    /** @var array */
    protected $unresolvedSelects = [];

    /** @var array */
    protected $unresolvedMultipleSelects = [];

    /** @var array */
    protected $unresolvedAttributes = [];

    /** @var CustomOptionTitle[] */
    protected $customOptionTitles = [];

    /** @var CustomOptionPrice[] */
    protected $customOptionPrices = [];

    /** @var CustomOptionValue[][] */
    protected $customOptionValues = [];

    public function setName(?string $name = null)
    {
        $this->attributes[self::ATTR_NAME] = ($name === null) ? null : trim($name);
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return array_key_exists(self::ATTR_NAME, $this->attributes) ? $this->attributes[self::ATTR_NAME] : null;
    }

    public function setStoreViewId(int $storeViewId)
    {
        $this->store_view_id = $storeViewId;
    }

    public function getStoreViewId()
    {
        return $this->store_view_id;
    }

    public function removeStoreViewId()
    {
        $this->store_view_id = null;
    }

    /**
     * Use the STATUS_ constants in this class.
     *
     * @param int|null $status
     */
    public function setStatus(?int $status = null)
    {
        $this->attributes[self::ATTR_STATUS] = $status;
    }

    /**
     * Use the AVAILABLE or NOT_AVAILABLE constants in this class.
     *
     * @param int|null $available
     */
    public function setGiftMessageAvailable(?int $available = null)
    {
        $this->attributes[self::ATTR_GIFT_MESSAGE_AVAILABLE] = $available;
    }

    public function setDescription(?string $description = null)
    {
        // textarea input: not trimmed
        $this->attributes[self::ATTR_DESCRIPTION] = $description;
    }

    public function setShortDescription(?string $shortDescription = null)
    {
        // textarea input: not trimmed
        $this->attributes[self::ATTR_SHORT_DESCRIPTION] = $shortDescription;
    }

    public function setMetaTitle(?string $metaTitle = null)
    {
        $this->attributes[self::ATTR_META_TITLE] = ($metaTitle === null) ? null : trim($metaTitle);
    }

    public function setMetaDescription(?string $metaDescription = null)
    {
        // textarea input: not trimmed
        $this->attributes[self::ATTR_META_DESCRIPTION] = $metaDescription;
    }

    public function setMetaKeywords(?string $metaKeywords = null)
    {
        // textarea input: not trimmed
        $this->attributes[self::ATTR_META_KEYWORDS] = $metaKeywords;
    }

    /**
     * @param string|null $price A 12.4 / 20.6 decimal field
     */
    public function setPrice(?string $price = null)
    {
        $this->attributes[self::ATTR_PRICE] = Decimal::formatPrice($price);
    }

    /**
     * @param string|null $cost A 12.4 decimal field
     */
    public function setCost(?string $cost = null)
    {
        $this->attributes[self::ATTR_COST] = Decimal::format($cost);
    }

    /**
     * @param string|null $msrp Manufacturer Suggested Retail Price. A 12.4 decimal field
     */
    public function setMsrp(?string $msrp = null)
    {
        $this->attributes[self::ATTR_MSRP] = Decimal::format($msrp);
    }

    /**
     * Check "MSRP" class constants for values.
     *
     * @param int|null $type
     */
    public function setMsrpDisplayActualPriceType(?int $type = null)
    {
        $this->attributes[self::ATTR_MSRP_DISPLAY_ACTUAL_PRICE_TYPE] = $type;
    }

    /**
     * Use one of the VISIBILITY_ constants of this class.
     *
     * @param int|null $visibility
     */
    public function setVisibility(?int $visibility = null)
    {
        $this->attributes[self::ATTR_VISIBILITY] = $visibility;
    }

    public function setTaxClassId(?int $taxClassId = null)
    {
        $this->attributes[self::ATTR_TAX_CLASS_ID] = $taxClassId;
    }

    public function setTaxClassName(?string $taxClassName = null)
    {
        $this->unresolvedAttributes[self::ATTR_TAX_CLASS_ID] = ($taxClassName === null) ? null : trim($taxClassName);
    }

    public function setUrlKey(?string $urlKey = null)
    {
        $this->attributes[self::ATTR_URL_KEY] = ($urlKey === null) ? null : trim($urlKey);
    }

    /**
     * @return string|GeneratedUrlKey|null
     */
    public function getUrlKey()
    {
        return array_key_exists(self::ATTR_URL_KEY, $this->attributes) ? $this->attributes[self::ATTR_URL_KEY] : null;
    }

    public function generateUrlKey()
    {
        $this->attributes[self::ATTR_URL_KEY] = new GeneratedUrlKey();
    }

    /**
     * @param string|null $weight A 12.4 decimal field
     */
    public function setWeight(?string $weight = null)
    {
        $this->attributes[self::ATTR_WEIGHT] = Decimal::format($weight);
    }

    /**
     * @param string|null $specialPrice A 12.4 decimal field
     */
    public function setSpecialPrice(?string $specialPrice = null)
    {
        $this->attributes[self::ATTR_SPECIAL_PRICE] = Decimal::formatPrice($specialPrice);
    }

    /**
     * @param string|null $specialPriceFromDate A y-m-d MySql date
     */
    public function setSpecialFromDate(?string $specialPriceFromDate = null)
    {
        $this->attributes[self::ATTR_SPECIAL_FROM_DATE] = ($specialPriceFromDate === null) ? null : trim($specialPriceFromDate);
    }

    /**
     * @param string|null $specialPriceToDate A y-m-d MySql date
     */
    public function setSpecialToDate(?string $specialPriceToDate = null)
    {
        $this->attributes[self::ATTR_SPECIAL_TO_DATE] = ($specialPriceToDate === null) ? null : trim($specialPriceToDate);
    }

    /**
     * @param string|null $newsFromDate A y-m-d MySql date
     */
    public function setNewsFromDate(?string $newsFromDate = null)
    {
        $this->attributes[self::ATTR_NEWS_FROM_DATE] = ($newsFromDate === null) ? null : trim($newsFromDate);
    }

    /**
     * @param string|null $newsToDate A y-m-d MySql date
     */
    public function setNewsToDate(?string $newsToDate = null)
    {
        $this->attributes[self::ATTR_NEWS_TO_DATE] = ($newsToDate === null) ? null : trim($newsToDate);
    }

    /**
     * @param string|null $option The admin name of the manufacturer attribute option
     */
    public function setManufacturer(?string $option = null)
    {
        $this->unresolvedSelects[self::ATTR_MANUFACTURER] = ($option === null) ? null : trim($option);
    }

    /**
     * @param string|null $countryCode 2 characters, uppercase
     */
    public function setCountryOfManufacture(?string $countryCode = null)
    {
        $this->attributes[self::ATTR_COUNTRY_OF_MANUFACTURE] = ($countryCode === null) ? null : trim($countryCode);
    }

    /**
     * @param string|null $option The admin name of the color attribute option
     */
    public function setColor(?string $option = null)
    {
        $this->unresolvedSelects[self::ATTR_COLOR] = ($option === null) ? null : trim($option);
    }

    /**
     * Set the value of a user defined attribute.
     * Apply trim() to $value before calling this function, if necessary.
     *
     * @param string $attributeCode
     * @param string|null $value
     */
    public function setCustomAttribute(string $attributeCode, ?string $value = null)
    {
        // value is not trimmed, because it may have textarea as input, or it may be null
        $this->attributes[trim($attributeCode)] = $value;
    }

    /**
     * @param $attributeCode
     * @return mixed|null|
     */
    public function getAttribute($attributeCode)
    {
        return array_key_exists($attributeCode, $this->attributes) ? $this->attributes[$attributeCode] : null;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return array
     */
    public function getUnresolvedAttributes()
    {
        return $this->unresolvedAttributes;
    }

    /**
     * @return array Attribute codes of selects whose option value names are given
     */
    public function getUnresolvedSelects()
    {
        return $this->unresolvedSelects;
    }

    /**
     * @return array Attribute codes of multiple selects whose option value names are given
     */
    public function getUnresolvedMultipleSelects()
    {
        return $this->unresolvedMultipleSelects;
    }

    /**
     * Removes an attribute from the import (not from the database).
     * @param string $name
     */
    public function removeAttribute(string $name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * @param Image $image Should be an image retrieved from $product->addImage() on the same product.
     * @param string $label Will be used as alt-tag on the product page
     * @param int $position Gallery position (1, 2, 3, ...)
     * @param bool $enabled Show on product page?
     */
    public function setImageGalleryInformation(Image $image, string $label, int $position, bool $enabled)
    {
        $this->imageGalleryInformation[] = new ImageGalleryInformation($image, $label, $position, $enabled);
    }

    public function getImageGalleryInformation()
    {
        return $this->imageGalleryInformation;
    }

    /**
     * Choose a "role" (image, small_image, thumbnail, swatch_image) for the image. Use one of this class' constants.
     *
     * @param Image $image Should be an image retrieved from $product->addImage() on the same product.
     * @param string $attributeCode A media_image attribute (use one of the class constants above, or a custom attribute code)
     */
    public function setImageRole(Image $image, string $attributeCode)
    {
        $this->imageRoles[$attributeCode] = $image;
    }

    /**
     * @return Image[]
     */
    public function getImageRoles()
    {
        return $this->imageRoles;
    }

    /**
     * @param string $attributeCode
     * @param string $option The admin name of the attribute option
     */
    public function setSelectAttribute(string $attributeCode, ?string $option = null)
    {
        $this->unresolvedSelects[trim($attributeCode)] = ($option === null) ? null : trim($option);
    }

    public function setSelectAttributeOptionId(string $attributeCode, $optionId = null)
    {
        $this->attributes[$attributeCode] = $optionId;
    }

    /**
     * @param string $attributeCode
     * @param array $options The admin names of the attribute options
     */
    public function setMultipleSelectAttribute(string $attributeCode, ?array $options = null)
    {
        $this->unresolvedMultipleSelects[trim($attributeCode)] = ($options === null) ? null : array_map('trim', $options);
    }

    public function setMultiSelectAttributeOptionIds(string $attributeCode, ?array $optionIds = null)
    {
        $this->attributes[$attributeCode] = ($optionIds === null) ? null : implode(',', array_map('trim', $optionIds));
    }

    /**
     * @param CustomOption $customOption
     * @param string $title
     */
    public function setCustomOptionTitle(CustomOption $customOption, string $title)
    {
        $this->customOptionTitles[] = new CustomOptionTitle($customOption, trim($title));
    }

    /**
     * @param CustomOption $customOption
     * @param string $price
     * @param string $priceType
     */
    public function setCustomOptionPrice(CustomOption $customOption, string $price, string $priceType)
    {
        $this->customOptionPrices[] = new CustomOptionPrice($customOption, trim($price), trim($priceType));
    }

    /**
     * @param CustomOption $customOption
     * @param CustomOptionValue[] $values
     */
    public function setCustomOptionValues(CustomOption $customOption, array $values)
    {
        $this->customOptionValues[$customOption->getUniqueKey()] = $values;
    }

    /**
     * @return CustomOptionTitle[]
     */
    public function getCustomOptionTitles(): array
    {
        return $this->customOptionTitles;
    }

    /**
     * @return CustomOptionPrice[]
     */
    public function getCustomOptionPrices(): array
    {
        return $this->customOptionPrices;
    }

    /**
     * @param CustomOption $customOption
     * @return CustomOptionValue[]
     */
    public function getCustomOptionValues(CustomOption $customOption): array
    {
        $key = $customOption->getUniqueKey();

        return isset($this->customOptionValues[$key]) ? $this->customOptionValues[$key] : [];
    }
}
