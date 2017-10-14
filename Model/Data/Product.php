<?php

namespace BigBridge\ProductImport\Model\Data;

use Magento\Catalog\Model\Product\Attribute\Source\Status;

/**
 * Product fields.
 * Use underscores, not camelcase, to keep close to import columns.
 *
 * @author Patrick van Bergen
 */
class Product
{
    const STATUS_ENABLED = Status::STATUS_ENABLED;
    const STATUS_DISABLED = Status::STATUS_DISABLED;

    /** @var  int */
    public $id;

    /** @var  int  */
    public $status = self::STATUS_ENABLED;

    /** @var  string */
    public $attribute_set_name = 'Default';

    /** @var  string */
    public $store_view_code = 'admin';

    /** @var  string 64 character */
    public $sku;

    /** @var  string */
    public $name;

    /** @var  string A 12.4 decimal field */
    public $price;
}