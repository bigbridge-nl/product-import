<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Resource\Reference\Reference;
use BigBridge\ProductImport\Model\Resource\Reference\References;

/**
 * Product fields.
 * Use underscores, not camelcase, to keep close to import columns.
 *
 * @author Patrick van Bergen
 */
abstract class Product
{
    const GLOBAL_STORE_VIEW_CODE = 'admin';

    const DEFAULT_STOCK_NAME = 'Default';

    const PLACEHOLDER_NAME = 'Linked Product Placeholder';
    const PLACEHOLDER_PRICE = '123456.78';

    /** @var  int */
    public $id;

    /** @var  string|Reference */
    protected $attribute_set_id;

    /** @var  string 64 character */
    protected $sku;

    /** @var int[]|References */
    protected $category_ids = [];

    /** @var array  */
    protected $website_ids = [];

    /** @var ProductStoreView[] */
    protected $storeViews = [];

    /** @var ProductStockItem[] */
    protected $stockItems = [];

    /** @var Image[] */
    protected $images = [];

    /** @var string[][] */
    protected $linkedProductSkus = [];

    /** @var int[][] */
    protected $linkedProductIds = [];

    // =========================================
    // importer data
    // =========================================

    /** @var  array */
    protected $errors = [];

    /** @var string  */
    public $lineNumber = "";

    public function __construct(string $sku)
    {
        $this->storeViews[self::GLOBAL_STORE_VIEW_CODE] = new ProductStoreView();
        $this->stockItems[self::DEFAULT_STOCK_NAME] = new ProductStockItem();
        $this->sku = trim($sku);
    }

    public function isOk(): bool
    {
        return empty($this->errors);
    }

    public function addError(string $error)
    {
        $this->errors[] = $error;
    }

    /**
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @param string $storeViewCode
     * @return ProductStoreView
     */
    public function storeView(string $storeViewCode) {
        $storeViewCode = trim($storeViewCode);
        if (!array_key_exists($storeViewCode, $this->storeViews)) {
            $this->storeViews[$storeViewCode] = new ProductStoreView();
        }
        return $this->storeViews[$storeViewCode];
    }

    /**
     * @return ProductStoreView
     */
    public function global() {
        return $this->storeViews[self::GLOBAL_STORE_VIEW_CODE];
    }

    /**
     * @return ProductStoreView[]
     */
    public function getStoreViews()
    {
        return $this->storeViews;
    }

    /**
     * @return ProductStockItem
     */
    public function defaultStockItem()
    {
        return $this->stockItems[self::DEFAULT_STOCK_NAME];
    }

    /**
     * @return ProductStockItem[]
     */
    public function getStockItems()
    {
        return $this->stockItems;
    }

    public function setCategoryIds(array $categoryIds)
    {
        $this->category_ids = $categoryIds;
    }

    /**
     * @return References|int[]
     */
    public function getCategoryIds()
    {
        return $this->category_ids;
    }

    /**
     * @param array $categoryNames An array of category name paths (i.e. ['Books/Novels', 'Books/Sci-Fi/Foreign'].
     */
    public function setCategoriesByGlobalName(array $categoryNames)
    {
        $this->category_ids = new References($categoryNames);
    }

    public function setAttributeSetId(int $attributeSetId)
    {
        $this->attribute_set_id = $attributeSetId;
    }

    /**
     * @return Reference|int|null
     */
    public function getAttributeSetId()
    {
        return $this->attribute_set_id;
    }

    public function removeAttributeSetId()
    {
        $this->attribute_set_id = null;
    }

    /**
     * @param string $attributeSetName An attribute set name
     */
    public function setAttributeSetByName(string $attributeSetName)
    {
        $this->attribute_set_id = new Reference($attributeSetName);
    }

    public function setWebsitesByCode(array $websiteCodes)
    {
        $this->website_ids = new References($websiteCodes);
    }

    /**
     * @param int[] $websiteIds
     */
    public function setWebsitesIds(array $websiteIds)
    {
        $this->website_ids = $websiteIds;
    }

    /**
     * @return int[]|References
     */
    public function getWebsiteIds()
    {
        return $this->website_ids;
    }

    public function removeWebsiteIds()
    {
        $this->website_ids = null;
    }

    /**
     * @param string $imagePath Absolute path to JPEG or PNG image
     * @return Image
     */
    public function addImage(string $imagePath)
    {
        $image = new Image($imagePath);
        $this->images[] = $image;
        return $image;
    }

    /**
     * @return Image[]
     */
    public function getImages()
    {
        return $this->images;
    }

    public function setRelatedProductSkus(array $skus)
    {
        $this->linkedProductSkus[LinkInfo::RELATED] = array_map('trim', $skus);
    }

    public function setUpSellProductSkus(array $skus)
    {
        $this->linkedProductSkus[LinkInfo::UP_SELL] = array_map('trim', $skus);
    }

    public function setCrossSellProductSkus(array $skus)
    {
        $this->linkedProductSkus[LinkInfo::CROSS_SELL] = array_map('trim', $skus);
    }

    public function setRelatedProductId(array $ids)
    {
        $this->linkedProductIds[LinkInfo::RELATED] = array_map('trim', $ids);
    }

    public function setUpSellProductIds(array $ids)
    {
        $this->linkedProductIds[LinkInfo::UP_SELL] = array_map('trim', $ids);
    }

    public function setCrossSellProductIds(array $ids)
    {
        $this->linkedProductIds[LinkInfo::CROSS_SELL] = array_map('trim', $ids);
    }

    public function getLinkedProductSkus(): array
    {
        return $this->linkedProductSkus;
    }

    /**
     * Returns the ids of linked products of the given type
     * A return of null denotes that the user has not specified any links, and existing links should not be modified
     *
     * @param string $linkType
     * @return int[]|null
     */
    public function getLinkedProductIds(string $linkType)
    {
        return array_key_exists($linkType, $this->linkedProductIds) ? $this->linkedProductIds[$linkType] : null;
    }
}
