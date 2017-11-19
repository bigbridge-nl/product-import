<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;

/**
 * Product fields.
 * Use underscores, not camelcase, to keep close to import columns.
 *
 * @author Patrick van Bergen
 */
abstract class Product
{
    const GLOBAL_STORE_VIEW_CODE = 'admin';

    // a collection of some commonly used constants

    const STATUS_ENABLED = Status::STATUS_ENABLED;
    const STATUS_DISABLED = Status::STATUS_DISABLED;

    const VISIBILITY_NOT_VISIBLE = Visibility::VISIBILITY_NOT_VISIBLE;
    const VISIBILITY_IN_CATALOG = Visibility::VISIBILITY_IN_CATALOG;
    const VISIBILITY_IN_SEARCH = Visibility::VISIBILITY_IN_SEARCH;
    const VISIBILITY_BOTH = Visibility::VISIBILITY_BOTH;


    /** @var  int */
    public $id;

    /** @var  string|Reference */
    public $attribute_set_id;

    /** @var  string 64 character */
    public $sku;

    /** @var int[]|References */
    public $category_ids = [];

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

    public function storeView(string $storeViewCode) {
        if (!array_key_exists($storeViewCode, $this->storeViews)) {
            $this->storeViews[$storeViewCode] = new ProductStoreView($storeViewCode);
        }
        return $this->storeViews[$storeViewCode];
    }

    public function global() {
        if (!array_key_exists(self::GLOBAL_STORE_VIEW_CODE, $this->storeViews)) {
            $this->storeViews[self::GLOBAL_STORE_VIEW_CODE] = new ProductStoreView(self::GLOBAL_STORE_VIEW_CODE);
        }
        return $this->storeViews[self::GLOBAL_STORE_VIEW_CODE];
    }

    public function getStoreViews()
    {
        return $this->storeViews;
    }
}