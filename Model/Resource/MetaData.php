<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Helper\Decimal;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\Serialize\JsonValueSerializer;
use BigBridge\ProductImport\Model\Resource\Serialize\SerializeValueSerializer;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadata;
use Magento\Store\Model\ScopeInterface;

/**
 * Pre-loads all meta data needed for the core processes once.
 *
 * @author Patrick van Bergen
 */
class MetaData
{
    const ENTITY_TYPE_TABLE = 'eav_entity_type';
    const PRODUCT_ENTITY_TABLE = 'catalog_product_entity';
    const WEEE_TABLE = 'weee_tax';
    const CATEGORY_ENTITY_TABLE = 'catalog_category_entity';
    const URL_REWRITE_TABLE = 'url_rewrite';
    const URL_REWRITE_PRODUCT_CATEGORY_TABLE = 'catalog_url_rewrite_product_category';
    const CATEGORY_PRODUCT_TABLE = 'catalog_category_product';
    const ATTRIBUTE_SET_TABLE = 'eav_attribute_set';
    const ATTRIBUTE_TABLE = 'eav_attribute';
    const ATTRIBUTE_OPTION_TABLE = 'eav_attribute_option';
    const ATTRIBUTE_OPTION_VALUE_TABLE = 'eav_attribute_option_value';
    const CATALOG_ATTRIBUTE_TABLE = 'catalog_eav_attribute';
    const STORE_TABLE = 'store';
    const SOURCE_TABLE = 'inventory_source';
    const WEBSITE_TABLE = 'store_website';
    const TAX_CLASS_TABLE = 'tax_class';
    const CUSTOMER_GROUP_TABLE = 'customer_group';
    const PRODUCT_WEBSITE_TABLE = 'catalog_product_website';
    const MEDIA_GALLERY_TABLE = 'catalog_product_entity_media_gallery';
    const MEDIA_GALLERY_VALUE_TO_ENTITY_TABLE = 'catalog_product_entity_media_gallery_value_to_entity';
    const MEDIA_GALLERY_VALUE_TABLE = 'catalog_product_entity_media_gallery_value';
    const STOCK_ITEM_TABLE = 'cataloginventory_stock_item';
    const SUPER_ATTRIBUTE_TABLE = 'catalog_product_super_attribute';
    const SUPER_ATTRIBUTE_LABEL_TABLE = 'catalog_product_super_attribute_label';
    const SUPER_LINK_TABLE = 'catalog_product_super_link';
    const RELATION_TABLE = 'catalog_product_relation';
    const LINK_TABLE = 'catalog_product_link';
    const LINK_ATTRIBUTE_TABLE = 'catalog_product_link_attribute';
    const LINK_ATTRIBUTE_INT_TABLE = 'catalog_product_link_attribute_int';
    const LINK_ATTRIBUTE_DECIMAL_TABLE = 'catalog_product_link_attribute_decimal';
    const LINK_TYPE_TABLE = 'catalog_product_link_type';
    const TIER_PRICE_TABLE = 'catalog_product_entity_tier_price';
    const DOWNLOADABLE_LINK_TABLE = 'downloadable_link';
    const DOWNLOADABLE_LINK_TITLE_TABLE = 'downloadable_link_title';
    const DOWNLOADABLE_LINK_PRICE_TABLE = 'downloadable_link_price';
    const DOWNLOADABLE_SAMPLE_TABLE = 'downloadable_sample';
    const DOWNLOADABLE_SAMPLE_TITLE_TABLE = 'downloadable_sample_title';
    const BUNDLE_OPTION_TABLE = 'catalog_product_bundle_option';
    const BUNDLE_OPTION_VALUE_TABLE = 'catalog_product_bundle_option_value';
    const BUNDLE_SELECTION_TABLE = 'catalog_product_bundle_selection';
    const CUSTOM_OPTION_TABLE = 'catalog_product_option';
    const CUSTOM_OPTION_PRICE_TABLE = 'catalog_product_option_price';
    const CUSTOM_OPTION_TITLE_TABLE = 'catalog_product_option_title';
    const CUSTOM_OPTION_TYPE_PRICE_TABLE = 'catalog_product_option_type_price';
    const CUSTOM_OPTION_TYPE_TITLE_TABLE = 'catalog_product_option_type_title';
    const CUSTOM_OPTION_TYPE_VALUE_TABLE = 'catalog_product_option_type_value';
    const INVENTORY_SOURCE_ITEM = 'inventory_source_item';
    const INVENTORY_LOW_STOCK_NOTIFICATION_CONFIGURATION = 'inventory_low_stock_notification_configuration';

    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  ScopeConfigInterface */
    protected $scopeConfig;

    /** @var string */
    public $magentoVersion;

    /** @var ValueSerializer */
    public $valueSerializer;

    /** @var string */
    public $entityTypeTable;

    /** @var string */
    public $productEntityTable;

    /** @var string */
    public $productEntityVarcharTable;

    /** @var string */
    public $weeeTable;

    /** @var string */
    public $categoryEntityTable;

    /** @var string */
    public $urlRewriteTable;

    /** @var string */
    public $urlRewriteProductCategoryTable;

    /** @var string */
    public $categoryProductTable;

    /** @var string */
    public $productWebsiteTable;

    /** @var string */
    public $mediaGalleryTable;

    /** @var string */
    public $mediaGalleryValueToEntityTable;

    /** @var string */
    public $mediaGalleryValueTable;

    /** @var string */
    public $stockItemTable;

    /** @var string */
    public $superAttributeTable;

    /** @var string */
    public $superAttributeLabelTable;

    /** @var string */
    public $superLinkTable;

    /** @var string */
    public $relationTable;

    /** @var string */
    public $attributeTable;

    /** @var string */
    public $catalogAttributeTable;

    /** @var string */
    public $attributeOptionTable;

    /** @var string */
    public $attributeOptionValueTable;

    /** @var string */
    public $attributeSetTable;

    /** @var string */
    public $storeTable;

    /** @var string */
    public $sourceTable;

    /** @var string */
    public $websiteTable;

    /** @var string */
    public $taxClassTable;

    /** @var string */
    public $customerGroupTable;

    /** @var string */
    public $linkTable;

    /** @var string */
    public $linkAttributeTable;

    /** @var string */
    public $linkAttributeIntTable;

    /** @var string */
    public $linkAttributeDecimalTable;

    /** @var string */
    public $linkTypeTable;

    /** @var string */
    public $tierPriceTable;

    /** @var string */
    public $downloadableLinkTable;

    /** @var string */
    public $downloadableLinkTitleTable;

    /** @var string */
    public $downloadableLinkPriceTable;

    /** @var string */
    public $downloadableSampleTable;

    /** @var string */
    public $downloadableSampleTitleTable;

    /** @var string */
    public $bundleOptionTable;

    /** @var string */
    public $bundleOptionValueTable;

    /** @var string */
    public $bundleSelectionTable;

    /** @var string */
    public $customOptionTable;

    /** @var string */
    public $customOptionPriceTable;

    /** @var string */
    public $customOptionTitleTable;

    /** @var string */
    public $customOptionTypePriceTable;

    /** @var string */
    public $customOptionTypeTitleTable;

    /** @var string */
    public $customOptionTypeValueTable;

    /** @var string */
    public $inventorySourceItem;

    /** @var string */
    public $inventoryLowStockNotificationConfiguration;

    /** @var int */
    public $defaultCategoryAttributeSetId;

    /** @var int */
    public $defaultProductAttributeSetId;

    /** @var array Maps attribute set name to id */
    public $productAttributeSetMap;

    /** @var array Maps tax class name to id */
    public $taxClassMap;

    /** @var array Maps store view code to id */
    public $storeViewMap;

    /** @var array Maps source code to source code */
    public $sourceCodeMap;

    /** @var array Maps store view id to website id */
    public $storeViewWebsiteMap;

    /** @var  array Maps website code to id */
    public $websiteMap;

    /** @var array Maps customer group name to id */
    public $customerGroupMap;

    /** @var int */
    public $productEntityTypeId;

    /** @var int */
    public $categoryEntityTypeId;

    /** @var EavAttributeInfo[] */
    public $productEavAttributeInfo;

    /** @var int */
    public $mediaGalleryAttributeId;

    /** @var array */
    public $categoryAttributeMap;

    /** @var string */
    public $productUrlSuffixes;

    /** @var string */
    public $categoryUrlSuffixes;

    /** @var bool Create 301 rewrite for older url_rewrite entries */
    public $saveRewritesHistory;

    /** @var LinkInfo[] */
    public $linkInfo;

    /** @var int[] */
    public $imageAttributeIds;

    /** @var string */
    public $weeeAttributeId;

    /**
     * MetaData constructor.
     *
     * @param Magento2DbConnection $db
     * @throws Exception
     */
    public function __construct(
        Magento2DbConnection $db,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->db = $db;
        $this->scopeConfig = $scopeConfig;

        $this->loadTables();
        $this->reloadCache();
    }

    /**
     * Makes Magento product table names and ids quickly available.
     * These values do not change as a result of the import,
     * but some of these values may changed by your custom code. So init() allows you to reload these values.
     *
     * @throws Exception
     */
    public function loadTables()
    {
        $this->entityTypeTable = $this->db->getFullTableName(self::ENTITY_TYPE_TABLE);
        $this->productEntityTable = $this->db->getFullTableName(self::PRODUCT_ENTITY_TABLE);
        $this->productEntityVarcharTable = sprintf("%s_%s", $this->productEntityTable, EavAttributeInfo::TYPE_VARCHAR);
        $this->weeeTable = $this->db->getFullTableName(self::WEEE_TABLE);
        $this->categoryEntityTable = $this->db->getFullTableName(self::CATEGORY_ENTITY_TABLE);
        $this->urlRewriteTable = $this->db->getFullTableName(self::URL_REWRITE_TABLE);
        $this->urlRewriteProductCategoryTable = $this->db->getFullTableName(self::URL_REWRITE_PRODUCT_CATEGORY_TABLE);
        $this->categoryProductTable = $this->db->getFullTableName(self::CATEGORY_PRODUCT_TABLE);
        $this->productWebsiteTable = $this->db->getFullTableName(self::PRODUCT_WEBSITE_TABLE);
        $this->mediaGalleryTable = $this->db->getFullTableName(self::MEDIA_GALLERY_TABLE);
        $this->mediaGalleryValueToEntityTable = $this->db->getFullTableName(self::MEDIA_GALLERY_VALUE_TO_ENTITY_TABLE);
        $this->mediaGalleryValueTable = $this->db->getFullTableName(self::MEDIA_GALLERY_VALUE_TABLE);
        $this->stockItemTable = $this->db->getFullTableName(self::STOCK_ITEM_TABLE);
        $this->superAttributeTable = $this->db->getFullTableName(self::SUPER_ATTRIBUTE_TABLE);
        $this->superAttributeLabelTable = $this->db->getFullTableName(self::SUPER_ATTRIBUTE_LABEL_TABLE);
        $this->superLinkTable = $this->db->getFullTableName(self::SUPER_LINK_TABLE);
        $this->relationTable = $this->db->getFullTableName(self::RELATION_TABLE);
        $this->attributeTable = $this->db->getFullTableName(self::ATTRIBUTE_TABLE);
        $this->catalogAttributeTable = $this->db->getFullTableName(self::CATALOG_ATTRIBUTE_TABLE);
        $this->attributeOptionTable = $this->db->getFullTableName(self::ATTRIBUTE_OPTION_TABLE);
        $this->attributeOptionValueTable = $this->db->getFullTableName(self::ATTRIBUTE_OPTION_VALUE_TABLE);
        $this->attributeSetTable = $this->db->getFullTableName(self::ATTRIBUTE_SET_TABLE);
        $this->storeTable = $this->db->getFullTableName(self::STORE_TABLE);
        $this->sourceTable = $this->db->getFullTableName(self::SOURCE_TABLE);
        $this->websiteTable = $this->db->getFullTableName(self::WEBSITE_TABLE);
        $this->taxClassTable = $this->db->getFullTableName(self::TAX_CLASS_TABLE);
        $this->linkTable = $this->db->getFullTableName(self::LINK_TABLE);
        $this->linkAttributeTable = $this->db->getFullTableName(self::LINK_ATTRIBUTE_TABLE);
        $this->linkAttributeIntTable = $this->db->getFullTableName(self::LINK_ATTRIBUTE_INT_TABLE);
        $this->linkAttributeDecimalTable = $this->db->getFullTableName(self::LINK_ATTRIBUTE_DECIMAL_TABLE);
        $this->linkTypeTable = $this->db->getFullTableName(self::LINK_TYPE_TABLE);
        $this->customerGroupTable = $this->db->getFullTableName(self::CUSTOMER_GROUP_TABLE);
        $this->tierPriceTable = $this->db->getFullTableName(self::TIER_PRICE_TABLE);
        $this->downloadableLinkTable = $this->db->getFullTableName(self::DOWNLOADABLE_LINK_TABLE);
        $this->downloadableLinkTitleTable = $this->db->getFullTableName(self::DOWNLOADABLE_LINK_TITLE_TABLE);
        $this->downloadableLinkPriceTable = $this->db->getFullTableName(self::DOWNLOADABLE_LINK_PRICE_TABLE);
        $this->downloadableSampleTable = $this->db->getFullTableName(self::DOWNLOADABLE_SAMPLE_TABLE);
        $this->downloadableSampleTitleTable = $this->db->getFullTableName(self::DOWNLOADABLE_SAMPLE_TITLE_TABLE);
        $this->bundleOptionTable = $this->db->getFullTableName(self::BUNDLE_OPTION_TABLE);
        $this->bundleOptionValueTable = $this->db->getFullTableName(self::BUNDLE_OPTION_VALUE_TABLE);
        $this->bundleSelectionTable = $this->db->getFullTableName(self::BUNDLE_SELECTION_TABLE);
        $this->customOptionTable = $this->db->getFullTableName(self::CUSTOM_OPTION_TABLE);
        $this->customOptionTitleTable = $this->db->getFullTableName(self::CUSTOM_OPTION_TITLE_TABLE);
        $this->customOptionPriceTable = $this->db->getFullTableName(self::CUSTOM_OPTION_PRICE_TABLE);
        $this->customOptionTypeTitleTable = $this->db->getFullTableName(self::CUSTOM_OPTION_TYPE_TITLE_TABLE);
        $this->customOptionTypePriceTable = $this->db->getFullTableName(self::CUSTOM_OPTION_TYPE_PRICE_TABLE);
        $this->customOptionTypeValueTable = $this->db->getFullTableName(self::CUSTOM_OPTION_TYPE_VALUE_TABLE);
        $this->inventorySourceItem = $this->db->getFullTableName(self::INVENTORY_SOURCE_ITEM);
        $this->inventoryLowStockNotificationConfiguration = $this->db->getFullTableName(self::INVENTORY_LOW_STOCK_NOTIFICATION_CONFIGURATION);
    }

    /**
     * @throws Exception
     */
    public function reloadCache()
    {
        $this->productEntityTypeId = $this->getProductEntityTypeId();
        $this->categoryEntityTypeId = $this->getCategoryEntityTypeId();

        $this->magentoVersion = $this->detectMagentoVersion();
        $this->valueSerializer = $this->getValueSerializer();

        $this->defaultCategoryAttributeSetId = $this->getDefaultCategoryAttributeSetId();
        $this->defaultProductAttributeSetId = $this->getDefaultProductAttributeSetId();

        $this->storeViewMap = $this->getStoreViewMap();
        $this->storeViewWebsiteMap = $this->getStoreViewWebsiteMap();
        $this->websiteMap = $this->getWebsiteMap();
        $this->taxClassMap = $this->getTaxClassMap();
        $this->customerGroupMap = $this->getCustomerGroupMap();
        $this->linkInfo = $this->getLinkInfo();
        $this->categoryAttributeMap = $this->getCategoryAttributeMap();
        $this->productAttributeSetMap = $this->getProductAttributeSetMap();
        $this->mediaGalleryAttributeId = $this->getMediaGalleryAttributeId();
        $this->productEavAttributeInfo = $this->getProductEavAttributeInfo();
        $this->imageAttributeIds = $this->getImageAttributeIds();
        $this->weeeAttributeId = $this->getWeeeAttributeId();

        $this->productUrlSuffixes = $this->getProductUrlSuffixes();
        $this->categoryUrlSuffixes = $this->getCategoryUrlSuffixes();
        $this->saveRewritesHistory = $this->getSaveRewritesHistory();

        if (version_compare($this->magentoVersion, "2.3.0") >= 0) {
            $this->sourceCodeMap = $this->getSourceCodeMap();
        }

        if (version_compare($this->magentoVersion, "2.4.0") >= 0) {
            $this->setNewPriceDecimals();
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function detectMagentoVersion()
    {
        // Note: this is the official version to determine the Magento version:
        //
        // $productMetadata = new \Magento\Framework\App\ProductMetadata();
        // $version = $productMetadata->getVersion();
        //
        // But it takes up to 0.2 seconds to execute, this is too long
        // See also https://magento.stackexchange.com/questions/96858/how-to-get-magento-version-in-magento2-equivalent-of-magegetversion
        //
        // However, if magento/magento2-base is not deployed, it falls back to the official method
        // See https://github.com/bigbridge-nl/product-import/issues/45

        $composerFile = BP . '/vendor/magento/magento2-base/composer.json';

        if (!file_exists($composerFile)) {
            $productMetadata = ObjectManager::getInstance()->get(ProductMetadata::class);
            $magentoVersion = $productMetadata->getVersion();
        } else if (preg_match('/"version": "([^\"]+)"/',
            file_get_contents($composerFile), $matches)) {
            $magentoVersion = $matches[1];
        } else {
            throw new Exception("Magento version could not be detected.");
        }

        return $magentoVersion;
    }

    protected function setNewPriceDecimals()
    {
        Decimal::$decimalPriceFormat = Decimal::DECIMAL_20_6_FORMAT;
        Decimal::$decimalPricePattern = Decimal::DECIMAL_20_6_PATTERN;

        Decimal::$decimalEavFormat = Decimal::DECIMAL_20_6_FORMAT;
        Decimal::$decimalEavPattern = Decimal::DECIMAL_20_6_PATTERN;
    }

    protected function getValueSerializer()
    {
        if (version_compare($this->magentoVersion, '2.2.0') >= 0) {
            return new JsonValueSerializer();
        } else {
            return new SerializeValueSerializer();
        }
    }

    /**
     * Returns the id of the default category attribute set id.
     *
     * @return int
     */
    protected function getDefaultCategoryAttributeSetId()
    {
        return $this->db->fetchSingleCell("SELECT `default_attribute_set_id` FROM {$this->entityTypeTable} WHERE `entity_type_code` = 'catalog_category'");
    }

    protected function getDefaultProductAttributeSetId()
    {
        return $this->db->fetchSingleCell("SELECT `default_attribute_set_id` FROM {$this->entityTypeTable} WHERE `entity_type_code` = 'catalog_product'");
    }

    /**
     * Returns the id of the product entity type.
     *
     * @return int
     */
    protected function getProductEntityTypeId()
    {
        return $this->db->fetchSingleCell("SELECT `entity_type_id` FROM {$this->entityTypeTable} WHERE `entity_type_code` = 'catalog_product'");
    }

    /**
     * Returns the id of the category entity type.
     *
     * @return int
     */
    protected function getCategoryEntityTypeId()
    {
        return $this->db->fetchSingleCell("SELECT `entity_type_id` FROM {$this->entityTypeTable} WHERE `entity_type_code` = 'catalog_category'");
    }

    /**
     * Returns a name => id map for product attribute sets.
     *
     * @return array
     */
    protected function getProductAttributeSetMap()
    {
        return $this->db->fetchMap(
            "SELECT `attribute_set_name`, `attribute_set_id` FROM {$this->attributeSetTable} WHERE `entity_type_id` = ?
        ", [
            $this->productEntityTypeId
        ]);
    }

    /**
     * Returns a code => id map for store views.
     *
     * @return array
     */
    protected function getStoreViewMap()
    {
        return $this->db->fetchMap("SELECT `code`, `store_id` FROM {$this->storeTable}");
    }

    /**
     * Returns the ids of all store views, except global.
     * @return array
     */
    public function getNonGlobalStoreViewIds()
    {
        return array_diff($this->storeViewMap, ['0']);
    }

    /**
     * Returns the codes of all store views, except global.
     * @return array
     */
    public function getNonGlobalStoreViewCodes()
    {
        return array_values(array_diff(array_keys($this->storeViewMap), [Product::GLOBAL_STORE_VIEW_CODE]));
    }

    /**
     * @param array $storeViewCodes
     * @return array
     * @throws Exception
     */
    public function getStoreViewIds(array $storeViewCodes)
    {
        $ids = [];
        foreach ($storeViewCodes as $code) {
            if (array_key_exists($code, $this->storeViewMap)) {
                $ids[] = $this->storeViewMap[$code];
            } else {
                throw new Exception("Store view code not found: " . $code);
            }
        }
        return $ids;
    }

    protected function getStoreViewWebsiteMap()
    {
        return $this->db->fetchMap("SELECT `store_id`, `website_id` FROM {$this->storeTable}");
    }

    protected function getSourceCodeMap()
    {
        if (!$this->db->fetchSingleCell("SHOW TABLES LIKE '{$this->sourceTable}'")) {
            return [];
        }

        return $this->db->fetchMap("SELECT `source_code`, `source_code` FROM {$this->sourceTable}");
    }

    /**
     * Returns a code => id map for websites.
     *
     * @return array
     */
    protected function getWebsiteMap()
    {
        return $this->db->fetchMap("SELECT `code`, `website_id` FROM {$this->websiteTable}");
    }

    /**
     * Returns a code => id map for tax classes.
     *
     * @return array
     */
    protected function getTaxClassMap()
    {
        return $this->db->fetchMap("SELECT `class_name`, `class_id` FROM {$this->taxClassTable}");
    }

    /**
     * Returns a customer code (name) => id array
     *
     * @return array
     */
    protected function getCustomerGroupMap()
    {
        return $this->db->fetchMap("SELECT `customer_group_code`, `customer_group_id` FROM {$this->customerGroupTable}");
    }

    /**
     * Returns a name => id map for category attributes.
     *
     * @return array
     */
    protected function getCategoryAttributeMap()
    {
        return $this->db->fetchMap(
            "SELECT `attribute_code`, `attribute_id` FROM {$this->attributeTable} WHERE `entity_type_id` = ?
        ", [
            $this->categoryEntityTypeId
        ]);
    }

    /**
     * @return array An attribute code indexed array of AttributeInfo
     */
    protected function getProductEavAttributeInfo()
    {
        $rows = $this->db->fetchAllAssoc("
            SELECT A.`attribute_id`, A.`attribute_code`, A.`is_required`, A.`backend_type`, A.`frontend_input`, C.`is_global`
            FROM {$this->attributeTable} A
            INNER JOIN {$this->catalogAttributeTable} C ON C.`attribute_id` = A.`attribute_id`
            WHERE A.`entity_type_id` = ? AND A.backend_type != 'static'
         ", [
            $this->productEntityTypeId
        ]);

        $info = [];
        foreach ($rows as $row) {

            $info[$row['attribute_code']] = new EavAttributeInfo(
                $row['attribute_code'],
                (int)$row['attribute_id'],
                (bool)$row['is_required'],
                $row['backend_type'],
                $this->productEntityTable . '_' . $row['backend_type'],
                $row['frontend_input'],
                $row['is_global']);
        }

        return $info;
    }

    protected function getImageAttributeIds()
    {
        return [
            $this->productEavAttributeInfo[ProductStoreView::BASE_IMAGE]->attributeId,
            $this->productEavAttributeInfo[ProductStoreView::SMALL_IMAGE]->attributeId,
            $this->productEavAttributeInfo[ProductStoreView::SWATCH_IMAGE]->attributeId,
            $this->productEavAttributeInfo[ProductStoreView::THUMBNAIL_IMAGE]->attributeId,
        ];
    }

    /**
     * @return string|null
     */
    protected function getWeeeAttributeId()
    {
        return $this->db->fetchSingleCell("
            SELECT A.`attribute_id`
            FROM {$this->attributeTable} A
            INNER JOIN {$this->catalogAttributeTable} C ON C.`attribute_id` = A.`attribute_id`
            WHERE A.`entity_type_id` = ? AND A.frontend_input = 'weee'
         ", [
            $this->productEntityTypeId
        ]);
    }

    protected function getMediaGalleryAttributeId()
    {
        $attributeTable = $this->db->getFullTableName(self::ATTRIBUTE_TABLE);

        return $this->db->fetchSingleCell("
            SELECT `attribute_id`
            FROM {$attributeTable}
            WHERE `entity_type_id` = ? AND attribute_code = 'media_gallery'
        ", [
            $this->productEntityTypeId
        ]);

    }

    protected function getProductUrlSuffixes()
    {
        $suffixes = [];
        foreach ($this->storeViewMap as $storeViewId) {
            $suffixes[$storeViewId] = $this->scopeConfig->getValue(
                'catalog/seo/product_url_suffix',
                ScopeInterface::SCOPE_STORES,
                $storeViewId
            );
        }

        return $suffixes;
    }

    protected function getCategoryUrlSuffixes()
    {
        $suffixes = [];
        foreach ($this->storeViewMap as $storeViewId) {
            $suffixes[$storeViewId] = $this->scopeConfig->getValue(
                'catalog/seo/category_url_suffix',
                ScopeInterface::SCOPE_STORES,
                $storeViewId
            );
        }

        return $suffixes;
    }

    protected function getSaveRewritesHistory()
    {
        $value = $this->scopeConfig->getValue(
            'catalog/seo/save_rewrites_history'
        );

        return is_null($value) ? true : (bool)$value;
    }

    protected function getLinkInfo()
    {
        $linkTypeIdRelation = $linkTypeIdUpSell = $linkTypeIdCrossSell = $linkTypeIdSuper = null;
        $linkRelationAttributeIdPosition = $linkUpSellAttributeIdPosition = $linkCrossSellAttributeIdPosition = null;
        $linkSuperAttributeIdPosition = $linkSuperAttributeIdDefaultQuantity = null;

        $rows = $this->db->fetchAllAssoc("
            SELECT `code`, `link_type_id`
            FROM `{$this->linkTypeTable}`
        ");

        foreach ($rows as $row) {
            switch ($row['code']) {
                case 'relation':
                    $linkTypeIdRelation = $row['link_type_id'];
                    break;
                case 'up_sell':
                    $linkTypeIdUpSell = $row['link_type_id'];
                    break;
                case 'cross_sell':
                    $linkTypeIdCrossSell = $row['link_type_id'];
                    break;
                case 'super':
                    $linkTypeIdSuper = $row['link_type_id'];
                    break;
            }
        }

        $rows = $this->db->fetchAllAssoc("
            SELECT `product_link_attribute_id`, `link_type_id`, `product_link_attribute_code`
            FROM `{$this->linkAttributeTable}`
        ");

        foreach ($rows as $row) {
            switch ($row['product_link_attribute_code']) {
                case 'position':
                    switch ($row['link_type_id']) {
                        case $linkTypeIdRelation:
                            $linkRelationAttributeIdPosition = $row['product_link_attribute_id'];
                            break;
                        case $linkTypeIdUpSell:
                            $linkUpSellAttributeIdPosition = $row['product_link_attribute_id'];
                            break;
                        case $linkTypeIdCrossSell:
                            $linkCrossSellAttributeIdPosition = $row['product_link_attribute_id'];
                            break;
                        case $linkTypeIdSuper:
                            $linkSuperAttributeIdPosition = $row['product_link_attribute_id'];
                            break;
                    }
                    break;
                case 'qty':
                    switch ($row['link_type_id']) {
                        case $linkTypeIdSuper:
                            $linkSuperAttributeIdDefaultQuantity = $row['product_link_attribute_id'];
                            break;
                    }

            }
        }

        return [
            LinkInfo::RELATED => new LinkInfo($linkTypeIdRelation, $linkRelationAttributeIdPosition),
            LinkInfo::UP_SELL => new LinkInfo($linkTypeIdUpSell, $linkUpSellAttributeIdPosition),
            LinkInfo::CROSS_SELL => new LinkInfo($linkTypeIdCrossSell, $linkCrossSellAttributeIdPosition),
            LinkInfo::SUPER => new LinkInfo($linkTypeIdSuper, $linkSuperAttributeIdPosition, $linkSuperAttributeIdDefaultQuantity)
        ];
    }
}
