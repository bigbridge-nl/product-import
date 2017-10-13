<?php

namespace BigBridge\ProductImport\Model\Data;

use Magento\Catalog\Model\Product\Attribute\Source\Status;

/**
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
    public $attributeSetName;

    /** @var  string */
    public $storeViewCode = 'admin';

    /** @var  string 64 character */
    public $sku;

    /** @var  string */
    public $name;

    /** @var  string A 12.4 decimal field */
    public $price;
}