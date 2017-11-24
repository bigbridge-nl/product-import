<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;

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
    public $attribute_set_id;

    /** @var  string 64 character */
    protected $sku;

    /** @var int[]|References */
    protected $category_ids = [];

    // =========================================
    // importer data
    // =========================================

    /** @var bool  */
    public $ok = true;

    /** @var  array */
    public $errors = [];

    /** @var string  */
    public $lineNumber = "";

    /** @var ProductStoreView[] */
    protected $storeViews = [];

    public function __construct(string $sku)
    {
        $this->sku = $sku;
    }

    public function getSku()
    {
        return $this->sku;
    }

    public function storeView(string $storeViewCode) {
        $storeViewCode = trim($storeViewCode);
        if (!array_key_exists($storeViewCode, $this->storeViews)) {
            $this->storeViews[$storeViewCode] = new ProductStoreView();
        }
        return $this->storeViews[$storeViewCode];
    }

    public function global() {
        if (!array_key_exists(self::GLOBAL_STORE_VIEW_CODE, $this->storeViews)) {
            $this->storeViews[self::GLOBAL_STORE_VIEW_CODE] = new ProductStoreView();
        }
        return $this->storeViews[self::GLOBAL_STORE_VIEW_CODE];
    }

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
}