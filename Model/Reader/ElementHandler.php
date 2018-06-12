<?php

namespace BigBridge\ProductImport\Model\Reader;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\BundleProductOption;
use BigBridge\ProductImport\Api\Data\BundleProductSelection;
use BigBridge\ProductImport\Api\Data\BundleProductStoreView;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\GroupedProductMember;
use BigBridge\ProductImport\Api\Data\ProductStockItem;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Importer;
use Exception;

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

    /** @var ProductStockItem */
    protected $defaultStockItem = null;

    /** @var string  */
    protected $characterData = "";

    /** @var string[] */
    protected $items = [];

    /** @var string[] */
    protected $elementPath = [self::ROOT];

    /** @var array[] */
    protected $attributePath = [[]];

    /** @var GroupedProductMember[] */
    protected $members = null;

    /** @var BundleProductOption[] */
    protected $options = null;

    /** @var BundleProductOption */
    protected $option = null;

    /** @var BundleProductSelection[] */
    protected $productSelections = null;
    
    /**
     * Attributes
     */
    const SKU = 'sku';
    const CODE = "code";
    const REMOVE = "remove";

    /**
     * Tags
     */
    const ROOT = "root";
    const IMPORT = "import";
    const PRODUCT = "product";
    const GLOBAL = "global";
    const STORE_VIEW = "store_view";
    const ATTRIBUTE_SET_NAME = "attribute_set_name";
    const CATEGORY_GLOBAL_NAMES = "category_global_names";
    const CATEGORY_IDS = "category_ids";
    const WEBSITE_CODES = "website_codes";
    const WEBSITE_IDS = "website_ids";
    const TAX_CLASS_NAME = "tax_class_name";
    const META_KEYWORDS = "meta_keywords";
    const GENERATE_URL_KEY = "generate_url_key";
    const CUSTOM = "custom";
    const SELECT = "select";
    const MULTI_SELECT = "multi_select";
    const STOCK = "stock";
    const ITEM = "item";
    const CROSS_SELL_PRODUCT_SKUS = "cross_sell_product_skus";
    const UP_SELL_PRODUCT_SKUS = "up_sell_product_skus";
    const RELATED_PRODUCT_SKUS = "related_product_skus";
    const SUPER_ATTRIBUTE_CODES = "super_attribute_codes";
    const VARIANT_SKUS = "variant_skus";
    const MEMBERS = "members";
    const MEMBER = "member";
    const OPTIONS = "options";
    const OPTION = "option";
    const PRODUCT_SELECTIONS = "product_selections";
    const PRODUCT_SELECTION = "product_selection";
    const OPTION_TITLE = "option_title";

    protected $multiAttributes = [
        self::CATEGORY_GLOBAL_NAMES,
        self::CATEGORY_IDS,
        self::WEBSITE_CODES,
        self::WEBSITE_IDS,
        self::MULTI_SELECT,
        self::CROSS_SELL_PRODUCT_SKUS,
        self::UP_SELL_PRODUCT_SKUS,
        self::RELATED_PRODUCT_SKUS,
        self::SUPER_ATTRIBUTE_CODES,
        self::VARIANT_SKUS,
    ];

    protected $productTypes = [
        SimpleProduct::TYPE_SIMPLE,
        VirtualProduct::TYPE_VIRTUAL,
        DownloadableProduct::TYPE_DOWNLOADABLE,
        ConfigurableProduct::TYPE_CONFIGURABLE,
        BundleProduct::TYPE_BUNDLE,
        GroupedProduct::TYPE_GROUPED,
    ];

    public function __construct(Importer $importer)
    {
        $this->importer = $importer;
    }

    /**
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
        $scope = $this->elementPath[count($this->elementPath) - 1];

        $this->elementPath[] = $element;
        $this->attributePath[] = $attributes;

        if ($scope === self::IMPORT) {
            if (in_array($element, $this->productTypes)) {
                $this->product = $this->createProduct($parser, $element, $attributes);
            }
        } elseif (in_array($scope, $this->productTypes)) {
            if ($element === self::GLOBAL) {
                $this->storeView = $this->product->global();
            } elseif ($element === self::STORE_VIEW) {
                $this->storeView = $this->product->storeView($attributes[self::CODE]);
            } elseif ($element === self::STOCK) {
                $this->defaultStockItem = $this->product->defaultStockItem();
            }

            if ($scope === GroupedProduct::TYPE_GROUPED) {
                if ($element === self::MEMBERS) {
                    $this->members = [];
                }
            } elseif ($scope === BundleProduct::TYPE_BUNDLE) {
                if ($element === self::OPTIONS) {
                    $this->options = [];
                }
            }
        } elseif ($scope === self::OPTIONS) {
            if ($element === self::OPTION) {
                $this->option = new BundleProductOption($attributes['input_type'], $attributes['required']);
            }
        } elseif ($scope === self::OPTION) {
            if ($element === self::PRODUCT_SELECTIONS) {
                $this->productSelections = [];
            } elseif ($element === self::GLOBAL) {
                $this->storeView = $this->product->global();
            } elseif ($element === self::STORE_VIEW) {
                $this->storeView = $this->product->storeView($attributes[self::CODE]);
            }
        }

        if (in_array($element, $this->multiAttributes)) {
            $this->items = [];
        }
    }

    /**
     * @param $parser
     * @param $data
     */
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
        $depth = count($this->elementPath);
        $scope = $this->elementPath[$depth - 2];
        $value = $this->characterData;
        $attributes = $this->attributePath[$depth - 1];

        if ($scope === self::IMPORT) {
            if (in_array($element, $this->productTypes)) {
                $this->importer->importAnyProduct($this->product);
            }
        } elseif (in_array($scope, $this->productTypes)) {
            if ($element === self::ATTRIBUTE_SET_NAME) {
                $this->product->setAttributeSetByName($value);
            } elseif ($element === self::CATEGORY_GLOBAL_NAMES) {
                $this->product->addCategoriesByGlobalName($this->items);
            } elseif ($element === self::CATEGORY_IDS) {
                $this->product->addCategoryIds($this->items);
            } elseif ($element === self::WEBSITE_CODES) {
                $this->product->setWebsitesByCode($this->items);
            } elseif ($element === self::WEBSITE_IDS) {
                $this->product->setWebsitesIds($this->items);
            } elseif ($element === self::CROSS_SELL_PRODUCT_SKUS) {
                $this->product->setCrossSellProductSkus($this->items);
            } elseif ($element === self::UP_SELL_PRODUCT_SKUS) {
                $this->product->setUpSellProductSkus($this->items);
            } elseif ($element === self::RELATED_PRODUCT_SKUS) {
                $this->product->setRelatedProductSkus($this->items);
            }

            if ($scope === ConfigurableProduct::TYPE_CONFIGURABLE) {
                /** @var ConfigurableProduct $configurable */
                $configurable = $this->product;

                if ($element === self::SUPER_ATTRIBUTE_CODES) {
                    $configurable->setSuperAttributeCodes($this->items);
                } elseif ($element === self::VARIANT_SKUS) {
                    $configurable->setVariantSkus($this->items);
                }
            } elseif ($scope === GroupedProduct::TYPE_GROUPED) {
                /** @var GroupedProduct $grouped */
                $grouped = $this->product;

                if ($element === self::MEMBERS) {
                    $grouped->setMembers($this->members);
                }
            } elseif ($scope === BundleProduct::TYPE_BUNDLE) {
                /** @var BundleProduct $bundle */
                $bundle = $this->product;

                if ($element === self::OPTIONS) {
                    $bundle->setOptions($this->options);
                }
            }

        } elseif ($scope === self::GLOBAL || $scope === self::STORE_VIEW) {

            if (array_key_exists(self::REMOVE, $attributes) && $attributes[self::REMOVE] === "1") {
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
            } elseif ($element === self::TAX_CLASS_NAME) {
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
            } elseif ($element === self::META_KEYWORDS) {
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
            } elseif ($element === self::SELECT) {
                $this->storeView->setSelectAttribute($attributes[self::CODE], $value);
            } elseif ($element === self::MULTI_SELECT) {
                $this->storeView->setMultipleSelectAttribute($attributes[self::CODE], $this->items);
            } elseif ($element === self::CUSTOM) {
                $this->storeView->setCustomAttribute($attributes[self::CODE], $value);
            }

            if ($this->storeView instanceof BundleProductStoreView) {
                if ($element === BundleProductStoreView::ATTR_PRICE_TYPE) {
                    $this->storeView->setPriceType($value);
                } elseif ($element === BundleProductStoreView::ATTR_SKU_TYPE) {
                    $this->storeView->setSkuType($value);
                } elseif ($element === BundleProductStoreView::ATTR_WEIGHT_TYPE) {
                    $this->storeView->setWeightType($value);
                } elseif ($element === BundleProductStoreView::ATTR_PRICE_VIEW) {
                    $this->storeView->setPriceView($value);
                } elseif ($element === BundleProductStoreView::ATTR_SHIPMENT_TYPE) {
                    $this->storeView->setShipmentType($value);
                }

                // bundle / options / option / global|storeview
                if ($element === self::OPTION_TITLE) {
                    $this->storeView->setOptionTitle($this->option, $value);
                }
            }

        } elseif (in_array($scope, $this->multiAttributes)) {
            if ($element === self::ITEM) {
                $this->items[] = $value;
            }
        } elseif ($scope === self::STOCK) {
            if ($element === ProductStockItem::QTY) {
                $this->defaultStockItem->setQty($value);
            } elseif ($element === ProductStockItem::IS_IN_STOCK) {
                $this->defaultStockItem->setIsInStock($value);
            } elseif ($element === ProductStockItem::MIN_QTY) {
                $this->defaultStockItem->setMinimumQuantity($value);
            } elseif ($element === ProductStockItem::NOTIFY_STOCK_QTY) {
                $this->defaultStockItem->setNotifyStockQuantity($value);
            } elseif ($element === ProductStockItem::MIN_SALE_QTY) {
                $this->defaultStockItem->setMinimumSaleQuantity($value);
            } elseif ($element === ProductStockItem::MAX_SALE_QTY) {
                $this->defaultStockItem->setMaximumSaleQuantity($value);
            } elseif ($element === ProductStockItem::QTY_INCREMENTS) {
                $this->defaultStockItem->setQuantityIncrements($value);
            } elseif ($element === ProductStockItem::LOW_STOCK_DATE) {
                $this->defaultStockItem->setLowStockDate($value);
            } elseif ($element === ProductStockItem::USE_CONFIG_MIN_QTY) {
                $this->defaultStockItem->setUseConfigMinimumQuantity($value);
            } elseif ($element === ProductStockItem::IS_QTY_DECIMAL) {
                $this->defaultStockItem->setIsQuantityDecimal($value);
            } elseif ($element === ProductStockItem::BACKORDERS) {
                $this->defaultStockItem->setBackorders($value);
            } elseif ($element === ProductStockItem::USE_CONFIG_BACKORDERS) {
                $this->defaultStockItem->setUseConfigBackorders($value);
            } elseif ($element === ProductStockItem::USE_CONFIG_MIN_SALE_QTY) {
                $this->defaultStockItem->setUseConfigMinimumSaleQuantity($value);
            } elseif ($element === ProductStockItem::USE_CONFIG_MAX_SALE_QTY) {
                $this->defaultStockItem->setUseConfigMaximumSaleQuantity($value);
            } elseif ($element === ProductStockItem::USE_CONFIG_NOTIFY_STOCK_QTY) {
                $this->defaultStockItem->setUseConfigNotifyStockQuantity($value);
            } elseif ($element === ProductStockItem::MANAGE_STOCK) {
                $this->defaultStockItem->setManageStock($value);
            } elseif ($element === ProductStockItem::USE_CONFIG_MANAGE_STOCK) {
                $this->defaultStockItem->setUseConfigManageStock($value);
            } elseif ($element === ProductStockItem::STOCK_STATUS_CHANGED_AUTO) {
                $this->defaultStockItem->setStockStatusChangedAuto($value);
            } elseif ($element === ProductStockItem::USE_CONFIG_QTY_INCREMENTS) {
                $this->defaultStockItem->setUseConfigQuantityIncrements($value);
            } elseif ($element === ProductStockItem::USE_CONFIG_ENABLE_QTY_INC) {
                $this->defaultStockItem->setUseConfigEnableQuantityIncrements($value);
            } elseif ($element === ProductStockItem::ENABLE_QTY_INCREMENTS) {
                $this->defaultStockItem->setEnableQuantityIncrements($value);
            } elseif ($element === ProductStockItem::IS_DECIMAL_DIVIDED) {
                $this->defaultStockItem->setIsDecimalDivided($value);
            }
        } elseif ($scope === self::MEMBERS) {
            if ($element === self::MEMBER) {
                $this->members[] = new GroupedProductMember($attributes['sku'], $attributes['default_quantity']);
            }
        } elseif ($scope === self::OPTIONS) {
            if ($element === self::OPTION) {
                $this->options[] = $this->option;
            }
        } elseif ($scope === self::OPTION) {
            if ($element === self::PRODUCT_SELECTIONS) {
                $this->option->setProductSelections($this->productSelections);
            }
        } elseif ($scope === self::PRODUCT_SELECTIONS) {
            if ($element === self::PRODUCT_SELECTION) {
                $this->productSelections[] = new BundleProductSelection($attributes['sku'], $attributes['is_default'],
                    $attributes['price_type'], $attributes['price_value'], $attributes['quantity'], $attributes['can_change_quantity']);
            }
        }

        array_pop($this->elementPath);
        array_pop($this->attributePath);

        $this->characterData = "";
    }

    /**
     * @param $parser
     * @param string $type
     * @param array $attributes
     * @return Product
     * @throws Exception
     */
    protected function createProduct($parser, string $type, array $attributes)
    {
        $sku = $attributes[self::SKU];

        if ($type === SimpleProduct::TYPE_SIMPLE) {
            $product = new SimpleProduct($sku);
        } elseif ($type === VirtualProduct::TYPE_VIRTUAL) {
            $product = new VirtualProduct($sku);
        } elseif ($type === DownloadableProduct::TYPE_DOWNLOADABLE) {
            $product = new VirtualProduct($sku);
        } elseif ($type === ConfigurableProduct::TYPE_CONFIGURABLE) {
            $product = new ConfigurableProduct($sku);
        } elseif ($type === BundleProduct::TYPE_BUNDLE) {
            $product = new BundleProduct($sku);
        } elseif ($type === GroupedProduct::TYPE_GROUPED) {
            $product = new GroupedProduct($sku);
        } else {
            throw new Exception("Unknown type: " . $type . " in line " . xml_get_current_line_number($parser));
        }

        $product->lineNumber = xml_get_current_line_number($parser);

        return $product;
    }
}