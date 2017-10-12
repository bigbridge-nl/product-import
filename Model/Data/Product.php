<?php

namespace BigBridge\ProductImport\Model\Data;

/**
 * @author Patrick van Bergen
 */
class Product
{
    /** @var  int */
    public $id;

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