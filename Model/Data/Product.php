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
    public $name;

    /** @var  string A 12.4 decimal field */
    public $price;

    /** @var  string 64 character */
    public $sku;

    /** @var  string */
    public $attributeSetName;
}