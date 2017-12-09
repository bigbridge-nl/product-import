<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Data\Image;
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

    // =========================================
    // importer data
    // =========================================

    /** @var  array */
    protected $errors = [];

    /** @var string  */
    public $lineNumber = "";

    /** @var ProductStoreView[] */
    protected $storeViews = [];

    /** @var Image[] */
    protected $images = [];

    public function __construct(string $sku)
    {
        $this->storeViews[self::GLOBAL_STORE_VIEW_CODE] = new ProductStoreView();
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
     * @param array $attributeSetName An attribute set name
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
     * @param string $imagePath
     * @param bool $enabled
     * @return Image
     */
    public function addImage(string $imagePath, bool $enabled)
    {
        $image = new Image($imagePath, $enabled);
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
}