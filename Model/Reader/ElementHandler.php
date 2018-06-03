<?php

namespace BigBridge\ProductImport\Model\Reader;

use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
use Exception;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Importer;

/**
 * @author Patrick van Bergen
 */
class ElementHandler
{
    /** @var Importer */
    protected $importer;

    /** @var Product */
    protected $product = null;

    /** @var ProductStoreView */
    protected $storeView = null;

    /** @var string|null */
    protected $tag = null;

    /** @var string  */
    protected $characterData = "";

    /** @var string[] */
    protected $items = [];

    /** @var string[] */
    protected $path = ['root'];
    
    /** @var array */
    protected $attributes = null;

    const TYPE = 'type';
    const SKU = 'sku';
    const ATTRIBUTE_SET_NAME = "attribute_set_name";
    const CODE = "code";

    const ATTR_TAX_CLASS_NAME = "tax_class_name";
    const GENERATE_URL_KEY = "generate_url_key";
    const ATTR_META_KEYWORDS = "meta_keywords";

    const REMOVE = "remove";

    const CUSTOM = "custom";

    public function __construct(Importer $importer)
    {
        $this->importer = $importer;
    }

    /**
     * Check if element exists in current scope.
     * Initialize data structures.
     *
     * @param $parser
     * @param $element
     * @param $attributes
     * @throws Exception
     */
    public function elementStart($parser, $element, $attributes)
    {
        $this->characterData = "";

        $scope = $this->path[count($this->path) - 1];
        $this->path[] = $element;
        $unknown = false;

        if ($scope === "root") {
            if ($element === "import") {
            } else {
                $unknown = true;
            }
        } elseif ($scope === "import") {
            if ($element === "product") {
                $this->product = $this->createProduct($parser, $attributes);
            } else {
                $unknown = true;
            }
        } elseif ($scope === "product") {
            if ($element === "global") {
                $this->storeView = $this->product->global();
            } elseif ($element === "store_view") {
                $this->storeView = $this->createStoreView($parser, $attributes, $this->product);
            } elseif ($element === "category_global_names") {
                $this->items = [];
            } elseif ($element === "category_ids") {
                $this->items = [];
            } elseif ($element === "website_codes") {
                $this->items = [];
            } elseif ($element === "website_ids") {
                $this->items = [];
            } else {
                $unknown = true;
            }
        } elseif ($scope === "global" || $scope === "store_view") {

            $this->attributes = $attributes;

            if (!in_array($element, [
                ProductStoreView::ATTR_NAME,
                ProductStoreView::ATTR_PRICE,
                ProductStoreView::ATTR_GIFT_MESSAGE_AVAILABLE,
                ProductStoreView::ATTR_STATUS,
                ProductStoreView::ATTR_VISIBILITY,
                ProductStoreView::ATTR_DESCRIPTION,
                ProductStoreView::ATTR_SHORT_DESCRIPTION,
                ProductStoreView::ATTR_META_TITLE,
                ProductStoreView::ATTR_META_DESCRIPTION,
                ProductStoreView::ATTR_COST,
                ProductStoreView::ATTR_MSRP,
                ProductStoreView::ATTR_MSRP_DISPLAY_ACTUAL_PRICE_TYPE,
                ProductStoreView::ATTR_URL_KEY,
                ProductStoreView::ATTR_WEIGHT,
                ProductStoreView::ATTR_SPECIAL_PRICE,
                ProductStoreView::ATTR_SPECIAL_FROM_DATE,
                ProductStoreView::ATTR_SPECIAL_TO_DATE,
                ProductStoreView::ATTR_NEWS_FROM_DATE,
                ProductStoreView::ATTR_NEWS_TO_DATE,
                ProductStoreView::ATTR_MANUFACTURER,
                ProductStoreView::ATTR_COUNTRY_OF_MANUFACTURE,
                ProductStoreView::ATTR_COLOR,
                self::ATTR_META_KEYWORDS,
                self::ATTR_TAX_CLASS_NAME,
                self::GENERATE_URL_KEY,
                self::CUSTOM
            ])) {
                $unknown = true;
            }
        } elseif ($scope === "category_global_names" || $scope === "category_ids" || $scope === "website_codes" || $scope === "website_ids") {
            if ($element === "item") {
            } else {
                $unknown = true;
            }
        } else {
            $unknown = true;
        }

        if ($unknown) {
            $line = xml_get_current_line_number($parser);
            throw new Exception("Unknown element '{$element}' in line {$line}");
        }
    }

    public function characterData($parser, $data)
    {
        $this->characterData .= $data;
    }

    /**
     * @param $parser
     * @param $element
     * @throws \Exception
     */
    public function elementEnd($parser, $element)
    {
        $scope = $this->path[count($this->path) - 2];
        $value = $this->characterData;
        
        if ($scope === "import") {
            if ($element === "product") {
                $this->importer->importAnyProduct($this->product);
            }
        } elseif ($scope === "product") {
            if ($element === "category_global_names") {
                $this->product->addCategoriesByGlobalName($this->items);
            } elseif ($element === "category_ids") {
                $this->product->addCategoryIds($this->items);
            } elseif ($element === "website_codes") {
                $this->product->setWebsitesByCode($this->items);
            } elseif ($element === "website_ids") {
                $this->product->setWebsitesIds($this->items);
            }
        } elseif ($scope === "global" || $scope === "store_view") {

            if (array_key_exists(self::REMOVE, $this->attributes) && $this->attributes[self::REMOVE] === "true") {
                $value = null;
            }

            if ($element === ProductStoreView::ATTR_NAME) {
                $this->storeView->setName($value);
            } elseif ($element === ProductStoreView::ATTR_PRICE) {
                $this->storeView->setPrice($value);
            } elseif ($element === ProductStoreView::ATTR_STATUS) {
                $this->storeView->setStatus($value);
            } elseif ($element === ProductStoreView::ATTR_VISIBILITY) {
                $this->storeView->setVisibility($value);
            } elseif ($element === self::ATTR_TAX_CLASS_NAME) {
                $this->storeView->setTaxClassName($value);
            } elseif ($element === ProductStoreView::ATTR_DESCRIPTION) {
                $this->storeView->setDescription($value);
            } elseif ($element === ProductStoreView::ATTR_SHORT_DESCRIPTION) {
                $this->storeView->setShortDescription($value);
            } elseif ($element === ProductStoreView::ATTR_URL_KEY) {
                $this->storeView->setUrlKey($value);
            } elseif ($element === self::GENERATE_URL_KEY) {
                if ($value === "1") {
                    $this->storeView->generateUrlKey();
                }
            } elseif ($element === ProductStoreView::ATTR_GIFT_MESSAGE_AVAILABLE) {
                $this->storeView->setGiftMessageAvailable($value);
            } elseif ($element === ProductStoreView::ATTR_META_TITLE) {
                $this->storeView->setMetaTitle($value);
            } elseif ($element === ProductStoreView::ATTR_META_DESCRIPTION) {
                $this->storeView->setMetaDescription($value);
            } elseif ($element === self::ATTR_META_KEYWORDS) {
                $this->storeView->setMetaKeywords($value);
            } elseif ($element === ProductStoreView::ATTR_COST) {
                $this->storeView->setCost($value);
            } elseif ($element === ProductStoreView::ATTR_MSRP) {
                $this->storeView->setMsrp($value);
            } elseif ($element === ProductStoreView::ATTR_MSRP_DISPLAY_ACTUAL_PRICE_TYPE) {
                $this->storeView->setMsrpDisplayActualPriceType($value);
            } elseif ($element === ProductStoreView::ATTR_WEIGHT) {
                $this->storeView->setWeight($value);
            } elseif ($element === ProductStoreView::ATTR_SPECIAL_PRICE) {
                $this->storeView->setSpecialPrice($value);
            } elseif ($element === ProductStoreView::ATTR_SPECIAL_FROM_DATE) {
                $this->storeView->setSpecialFromDate($value);
            } elseif ($element === ProductStoreView::ATTR_SPECIAL_TO_DATE) {
                $this->storeView->setSpecialToDate($value);
            } elseif ($element === ProductStoreView::ATTR_NEWS_FROM_DATE) {
                $this->storeView->setNewsFromDate($value);
            } elseif ($element === ProductStoreView::ATTR_NEWS_TO_DATE) {
                $this->storeView->setNewsToDate($value);
            } elseif ($element === ProductStoreView::ATTR_MANUFACTURER) {
                $this->storeView->setManufacturer($value);
            } elseif ($element === ProductStoreView::ATTR_COUNTRY_OF_MANUFACTURE) {
                $this->storeView->setCountryOfManufacture($value);
            } elseif ($element === ProductStoreView::ATTR_COLOR) {
                $this->storeView->setColor($value);
            } elseif ($element === self::CUSTOM) {
                $this->setCustomAttribute($parser, $this->attributes, $value);
            }
        } elseif ($scope === "category_global_name" || $scope === "category_ids" || $scope === "website_codes") {
            if ($element === "item") {
                $this->items[] = $value;
            }
        }

        array_pop($this->path);

        $this->characterData = "";
        $this->remove = false;
    }

    /**
     * @param $parser
     * @param array $attributes
     * @return Product
     * @throws Exception
     */
    protected function createProduct($parser, array $attributes)
    {
        if (!isset($attributes[self::TYPE])) {
            throw new Exception("Missing type in line " . xml_get_current_line_number($parser));
        } elseif (!isset($attributes[self::SKU])) {
            throw new Exception("Missing sku in line " . xml_get_current_line_number($parser));
        } else {

            $type = $attributes[self::TYPE];
            $sku = $attributes[self::SKU];

            if ($type === SimpleProduct::TYPE_SIMPLE) {
                $product = new SimpleProduct($sku);
            } elseif ($type === VirtualProduct::TYPE_VIRTUAL) {
                $product = new VirtualProduct($sku);
            } elseif ($type === DownloadableProduct::TYPE_DOWNLOADABLE) {
                $product = new VirtualProduct($sku);
            } else {
                throw new Exception("Unknown type: " . $attributes[self::TYPE] . " in line " . xml_get_current_line_number($parser));
            }

            $product->lineNumber = xml_get_current_line_number($parser);

            if (isset($attributes[self::ATTRIBUTE_SET_NAME])) {
                $attributeSetName = $attributes[self::ATTRIBUTE_SET_NAME];
                $product->setAttributeSetByName($attributeSetName);
            }

            return $product;
        }
    }

    /**
     * @param $parser
     * @param $attributes
     * @param Product $product
     * @return ProductStoreView
     * @throws Exception
     */
    protected function createStoreView($parser, $attributes, Product $product)
    {
        if (!isset($attributes[self::CODE])) {
            throw new Exception("Missing code in line " . xml_get_current_line_number($parser));
        } else {
            $storeView = $product->storeView($attributes[self::CODE]);
            return $storeView;
        }
    }

    /**
     * @param $parser
     * @param $attributes
     * @param $value
     * @throws Exception
     */
    protected function setCustomAttribute($parser, $attributes, $value)
    {
        if (!array_key_exists(self::CODE, $attributes)) {
            throw new Exception("Missing code in line " . xml_get_current_line_number($parser));
        } else {
            $attributeCode = $attributes[self::CODE];
            $this->storeView->setCustomAttribute($attributeCode, $value);
        }
    }
}