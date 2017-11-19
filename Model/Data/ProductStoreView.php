<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Model\Reference;

/**
 * @author Patrick van Bergen
 */
class ProductStoreView
{
    /** @var  Product */
    public $parent;

    /** @var  int */
    public $store_view_id;

    /** @var string  */
    public $storeViewCode;

    public $visibility;

    public $website_ids = [];

    /** @var  string */
    public $name;

    /** @var  string A 12.4 decimal field */
    public $price;

    /** @var  int|Reference */
    public $tax_class_id;

    /** @var  string */
    public $url_key;

    public function __construct(Product $parent, string $storeViewCode)
    {
        $this->parent = $parent;
        $this->storeViewCode = $storeViewCode;
    }
}