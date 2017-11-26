<?php

namespace BigBridge\ProductImport\Model\Data;

/**
 * @author Patrick van Bergen
 */
class CategoryInfo
{
    /** @var  int[] An array of category ids leading to this category */
    public $path;

    /** @var  string[] An array of store-id => url_key */
    public $urlKeys;

    public function __construct(array $path, array $urlKeys)
    {
        $this->path = $path;
        $this->urlKeys = $urlKeys;
    }
}