<?php

namespace BigBridge\ProductImport\Model\Reader;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\BundleProductOption;
use BigBridge\ProductImport\Api\Data\BundleProductSelection;
use BigBridge\ProductImport\Api\Data\BundleProductStoreView;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\CustomOption;
use BigBridge\ProductImport\Api\Data\CustomOptionValue;
use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\DownloadableProductStoreView;
use BigBridge\ProductImport\Api\Data\DownloadLink;
use BigBridge\ProductImport\Api\Data\DownloadSample;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\GroupedProductMember;
use BigBridge\ProductImport\Api\Data\ProductStockItem;
use BigBridge\ProductImport\Api\Data\SourceItem;
use BigBridge\ProductImport\Api\Data\TierPrice;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Data\Weee;
use BigBridge\ProductImport\Api\Importer;
use BigBridge\ProductImport\Model\Data\Image;
use Exception;

/**
 * Processes all xml elements.
 *
 * @author Patrick van Bergen
 */
class ElementHandler
{
    /** @var Importer */
    protected $importer;

    /**
     * XML processing
     */

    /** @var string */
    protected $characterData;

    /** @var string[] */
    protected $items;

    /** @var string[] */
    protected $elementPath = [self::ROOT];

    /** @var array[] */
    protected $attributePath = [[]];

    /**
     * Context items
     */

    /** @var Product */
    protected $product;

    /** @var ProductStoreView */
    protected $storeView;

    /** @var ProductStockItem */
    protected $defaultStockItem;

    /** @var SourceItem */
    protected $sourceItem;

    /** @var GroupedProductMember[] */
    protected $members;

    /** @var BundleProductOption[] */
    protected $options;

    /** @var BundleProductOption */
    protected $option;

    /** @var BundleProductSelection[] */
    protected $productSelections;

    /** @var DownloadLink[] */
    protected $downloadLinks;

    /** @var DownloadLink */
    protected $downloadLink;

    /** @var DownloadSample[] */
    protected $downloadSamples;

    /** @var DownloadSample */
    protected $downloadSample;

    /** @var Image[] */
    protected $images;

    /** @var Image */
    protected $image;

    /** @var Weee[] */
    protected $weees;

    /** @var TierPrice[] */
    protected $tierPrices;

    /** @var CustomOption[] */
    protected $customOptions;

    /** @var CustomOption */
    protected $customOption;

    /** @var CustomOptionValue[] */
    protected $customOptionValues;

    /** @var string[] */
    protected $skuValues;

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
    const SOURCE_ITEM = "source_item";
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
    const DOWNLOAD_LINKS = "download_links";
    const DOWNLOAD_LINK = "download_link";
    const DOWNLOAD_LINK_INFORMATION = "download_link_information";
    const DOWNLOAD_SAMPLES = "download_samples";
    const DOWNLOAD_SAMPLE = "download_sample";
    const DOWNLOAD_SAMPLE_INFORMATION = "download_sample_information";
    const IMAGES = "images";
    const IMAGE = "image";
    const WEEES = 'weees';
    const WEEE = 'weee';
    const GALLERY_INFORMATION = "gallery_information";
    const ROLE = "role";
    const TIER_PRICES = "tier_prices";
    const TIER_PRICE = "tier_price";
    const CUSTOM_OPTIONS = "custom_options";
    const CUSTOM_OPTION_TEXTFIELD = "custom_option_textfield";
    const CUSTOM_OPTION_TEXTAREA = "custom_option_textarea";
    const CUSTOM_OPTION_FILE = 'custom_option_file';
    const CUSTOM_OPTION_DATE = "custom_option_date";
    const CUSTOM_OPTION_DATETIME = "custom_option_datetime";
    const CUSTOM_OPTION_TIME = "custom_option_time";
    const CUSTOM_OPTION_DROPDOWN = "custom_option_dropdown";
    const CUSTOM_OPTION_RADIO_BUTTONS = "custom_option_radio_buttons";
    const CUSTOM_OPTION_CHECKBOX_GROUP = "custom_option_checkbox_group";
    const CUSTOM_OPTION_MULTIPLE_SELECT = "custom_option_multiple_select";
    const CUSTOM_OPTION_TITLE = "custom_option_title";
    const CUSTOM_OPTION_PRICE = "custom_option_price";
    const CUSTOM_OPTION_VALUE = "custom_option_value";
    const CUSTOM_OPTION_VALUES = "custom_option_values";
    const SKU_VALUES = "sku_values";
    const DELETE = "delete";

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
        self::SKU_VALUES
    ];

    protected $productTypes = [
        SimpleProduct::TYPE_SIMPLE,
        VirtualProduct::TYPE_VIRTUAL,
        DownloadableProduct::TYPE_DOWNLOADABLE,
        ConfigurableProduct::TYPE_CONFIGURABLE,
        BundleProduct::TYPE_BUNDLE,
        GroupedProduct::TYPE_GROUPED,
    ];

    protected $customOptionElements = [
        self::CUSTOM_OPTION_TEXTFIELD,
        self::CUSTOM_OPTION_TEXTAREA,
        self::CUSTOM_OPTION_FILE,
        self::CUSTOM_OPTION_DATE,
        self::CUSTOM_OPTION_DATETIME,
        self::CUSTOM_OPTION_TIME,
        self::CUSTOM_OPTION_DROPDOWN,
        self::CUSTOM_OPTION_RADIO_BUTTONS,
        self::CUSTOM_OPTION_CHECKBOX_GROUP,
        self::CUSTOM_OPTION_MULTIPLE_SELECT,
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

            // fix the fact that the XSD validator allows skipping the <import> tag altogether
            if ($this->product === null) {
                throw new Exception("Missing top level <import> element");
            }

            if ($element === self::GLOBAL) {
                $this->storeView = $this->product->global();
            } elseif ($element === self::STORE_VIEW) {
                $this->storeView = $this->product->storeView($attributes['code']);
            } elseif ($element === self::STOCK) {
                $this->defaultStockItem = $this->product->defaultStockItem();
            } elseif ($element === self::SOURCE_ITEM) {
                $this->sourceItem = $this->product->sourceItem($attributes['code']);
            } elseif ($element === self::IMAGES) {
                $this->images = [];
            } elseif ($element === self::TIER_PRICES) {
                $this->tierPrices = [];
            } elseif ($element === self::CUSTOM_OPTIONS) {
                $this->customOptions = [];
            } elseif ($element === self::WEEES) {
                $this->weees = [];
            }

            if ($scope === GroupedProduct::TYPE_GROUPED) {
                if ($element === self::MEMBERS) {
                    $this->members = [];
                }
            } elseif ($scope === BundleProduct::TYPE_BUNDLE) {
                if ($element === self::OPTIONS) {
                    $this->options = [];
                }
            } elseif ($scope === DownloadableProduct::TYPE_DOWNLOADABLE) {
                if ($element === self::DOWNLOAD_LINKS) {
                    $this->downloadLinks = [];
                }
                if ($element === self::DOWNLOAD_SAMPLES) {
                    $this->downloadSamples = [];
                }
            }

        } elseif ($scope === self::GLOBAL || $scope === self::STORE_VIEW) {
            if ($element === self::CUSTOM_OPTION_VALUES) {
                $this->customOptionValues = [];
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
                $this->storeView = $this->product->storeView($attributes['code']);
            }
        } elseif ($scope === self::DOWNLOAD_LINKS) {
            if ($element === self::DOWNLOAD_LINK) {
                $this->downloadLink = new DownloadLink($attributes['file_or_url'], $attributes['number_of_downloads'],
                    $attributes['is_shareable'], $attributes['sample_file_or_url']);
            }
        } elseif ($scope === self::DOWNLOAD_SAMPLES) {
            if ($element === self::DOWNLOAD_SAMPLE) {
                $this->downloadSample = new DownloadSample($attributes['file_or_url']);
            }
        } elseif ($scope === self::DOWNLOAD_LINK || $scope === self::DOWNLOAD_SAMPLE) {
            if ($element === self::GLOBAL) {
                $this->storeView = $this->product->global();
            } elseif ($element === self::STORE_VIEW) {
                $this->storeView = $this->product->storeView($attributes['code']);
            }
        } elseif ($scope === self::IMAGES) {
            if ($element === self::IMAGE) {
                $this->image = $this->product->addImage($attributes['file_or_url']);
            }
        } elseif ($scope === self::IMAGE) {
            if ($element === self::GLOBAL) {
                $this->storeView = $this->product->global();
            } elseif ($element === self::STORE_VIEW) {
                $this->storeView = $this->product->storeView($attributes['code']);
            }
        } elseif ($scope === self::CUSTOM_OPTIONS) {
            if ($element === self::CUSTOM_OPTION_TEXTFIELD) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionTextField($attributes['sku'],
                    $attributes['required'], $attributes['max_characters']);
            } elseif ($element === self::CUSTOM_OPTION_TEXTAREA) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionTextArea($attributes['sku'],
                    $attributes['required'], $attributes['max_characters']);
            } elseif ($element === self::CUSTOM_OPTION_FILE) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionFile($attributes['sku'],
                    $attributes['required'], $attributes['file_extensions'], $attributes['max_width'], $attributes['max_height']);
            } elseif ($element === self::CUSTOM_OPTION_DATE) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionDate($attributes['sku'], $attributes['required']);
            } elseif ($element === self::CUSTOM_OPTION_DATETIME) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionDateTime($attributes['sku'], $attributes['required']);
            } elseif ($element === self::CUSTOM_OPTION_TIME) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionTime($attributes['sku'], $attributes['required']);
            } elseif ($element === self::CUSTOM_OPTION_DROPDOWN) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionDropDown($attributes['required'], []);
            } elseif ($element === self::CUSTOM_OPTION_RADIO_BUTTONS) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionRadioButtons($attributes['required'], []);
            } elseif ($element === self::CUSTOM_OPTION_CHECKBOX_GROUP) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionCheckboxGroup($attributes['required'], []);
            } elseif ($element === self::CUSTOM_OPTION_MULTIPLE_SELECT) {
                $this->customOptions[] = $this->customOption = CustomOption::createCustomOptionMultipleSelect($attributes['required'], []);
            }
        } elseif (in_array($scope, $this->customOptionElements)) {
            if ($element === self::GLOBAL) {
                $this->storeView = $this->product->global();
            } elseif ($element === self::STORE_VIEW) {
                $this->storeView = $this->product->storeView($attributes['code']);
            } elseif ($element === self::SKU_VALUES) {
                $this->skuValues = [];
            }
        } elseif ($scope === self::WEEES) {
            if ($element === self::WEEE) {
                $websiteId = isset($attributes['website_id']) ? $attributes['website_id'] : null;
                $state = isset($attributes['state']) ? $attributes['state'] : null;
                $this->weees[] = Weee::createWeee($attributes['country'], $attributes['value'], $websiteId, $state);
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
            } elseif ($element === self::TIER_PRICES) {
                $this->product->setTierPrices($this->tierPrices);
            } elseif ($element === self::CUSTOM_OPTIONS) {
                $this->product->setCustomOptions($this->customOptions);
            } elseif ($element === self::WEEES) {
                $this->product->setWeees($this->weees);
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
            } elseif ($scope === DownloadableProduct::TYPE_DOWNLOADABLE) {
                /** @var DownloadableProduct $downloadable */
                $downloadable = $this->product;

                if ($element === self::DOWNLOAD_LINKS) {
                    $downloadable->setDownloadLinks($this->downloadLinks);
                } elseif ($element === self::DOWNLOAD_SAMPLES) {
                    $downloadable->setDownloadSamples($this->downloadSamples);
                }
            }

        } elseif ($scope === self::GLOBAL || $scope === self::STORE_VIEW) {

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
            } elseif ($element === self::DELETE) {
                $this->storeView->setCustomAttribute($attributes['code'], null);
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
                $this->storeView->setSelectAttribute($attributes['code'], $value);
            } elseif ($element === self::MULTI_SELECT) {
                $this->storeView->setMultipleSelectAttribute($attributes['code'], $this->items);
            } elseif ($element === self::CUSTOM) {
                $this->storeView->setCustomAttribute($attributes['code'], $value);
            } elseif ($element === self::GALLERY_INFORMATION) {
                $this->storeView->setImageGalleryInformation($this->image, $attributes['label'], $attributes['position'], $attributes['enabled']);
            } elseif ($element === self::ROLE) {
                $this->storeView->setImageRole($this->image, $value);
            } elseif ($element === self::CUSTOM_OPTION_TITLE) {
                $this->storeView->setCustomOptionTitle($this->customOption, $value);
            } elseif ($element === self::CUSTOM_OPTION_PRICE) {
                $this->storeView->setCustomOptionPrice($this->customOption, $attributes['price'], $attributes['price_type']);
            } elseif ($element === self::CUSTOM_OPTION_VALUES) {
                $this->storeView->setCustomOptionValues($this->customOption, $this->customOptionValues);
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

                // bundle / options / option / global|store_view
                if ($element === self::OPTION_TITLE) {
                    $this->storeView->setOptionTitle($this->option, $value);
                }

            } elseif ($this->storeView instanceof DownloadableProductStoreView) {
                if ($element === DownloadableProductStoreView::ATTR_LINKS_PURCHASED_SEPARATELY) {
                    $this->storeView->setLinksPurchasedSeparately($value);
                } elseif ($element === DownloadableProductStoreView::ATTR_LINKS_TITLE) {
                    $this->storeView->setLinksTitle($value);
                } elseif ($element === DownloadableProductStoreView::ATTR_SAMPLES_TITLE) {
                    $this->storeView->setSamplesTitle($value);
                }

                // downloadable / download_links / download_link / global|store_view
                if ($element === self::DOWNLOAD_LINK_INFORMATION) {
                    $this->storeView->setDownloadLinkInformation($this->downloadLink, $attributes['title'], $attributes['price']);
                } elseif ($element === self::DOWNLOAD_SAMPLE_INFORMATION) {
                    $this->storeView->setDownloadSampleInformation($this->downloadSample, $attributes['title']);
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
        } elseif ($scope === self::SOURCE_ITEM) {
            if ($element === SourceItem::QUANTITY) {
                $this->sourceItem->setQuantity($value);
            } elseif ($element === SourceItem::STATUS) {
                $this->sourceItem->setStatus($value);
            } elseif ($element === SourceItem::NOTIFY_STOCK_QTY) {
                $this->sourceItem->setNotifyStockQuantity($value);
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
        } elseif ($scope === self::DOWNLOAD_LINKS) {
            if ($element === self::DOWNLOAD_LINK) {
                $this->downloadLinks[] = $this->downloadLink;
            }
        } elseif ($scope === self::DOWNLOAD_SAMPLES) {
            if ($element === self::DOWNLOAD_SAMPLE) {
                $this->downloadSamples[] = $this->downloadSample;
            }
        } elseif ($scope === self::TIER_PRICES) {
            if ($element === self::TIER_PRICE) {
                $customerGroupName = isset($attributes['customer_group_name']) ? $attributes['customer_group_name'] : null;
                $websiteCode = isset($attributes['website_code']) ? $attributes['website_code'] : null;
                $percentageValue = isset($attributes['percentage_value']) ? $attributes['percentage_value'] : null;
                $this->tierPrices[] = new TierPrice($attributes['qty'], $attributes['value'], $customerGroupName, $websiteCode, $percentageValue);
            }
        } elseif (in_array($scope, $this->customOptionElements)) {
            if ($element === self::SKU_VALUES) {
                $this->customOption->setValueSkus($this->items);
            }
        } elseif ($scope === self::CUSTOM_OPTION_VALUES) {
            if ($element === self::CUSTOM_OPTION_VALUE) {
                $this->customOptionValues[] = new CustomOptionValue($attributes['price'],
                    $attributes['price_type'], $attributes['title']);
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
        $sku = $attributes['sku'];

        if ($type === SimpleProduct::TYPE_SIMPLE) {
            $product = new SimpleProduct($sku);
        } elseif ($type === VirtualProduct::TYPE_VIRTUAL) {
            $product = new VirtualProduct($sku);
        } elseif ($type === DownloadableProduct::TYPE_DOWNLOADABLE) {
            $product = new DownloadableProduct($sku);
        } elseif ($type === ConfigurableProduct::TYPE_CONFIGURABLE) {
            $product = new ConfigurableProduct($sku);
        } elseif ($type === BundleProduct::TYPE_BUNDLE) {
            $product = new BundleProduct($sku);
        } elseif ($type === GroupedProduct::TYPE_GROUPED) {
            $product = new GroupedProduct($sku);
        } else {
            throw new Exception("Unknown product: " . $type);
        }

        if (isset($attributes['id'])) {
            $product->id = $attributes['id'];
        }

        $product->lineNumber = xml_get_current_line_number($parser);

        return $product;
    }
}