<?php

namespace BigBridge\ProductImport\Model\Resource;

/**
 * @author Patrick van Bergen
 */
class UrlRewriteInfo
{
    /** @var  string */
    public $productId;

    /** @var  string */
    public $requestPath;

    /** @var  string */
    public $targetPath;

    /** @var  int|string 0 or 301 */
    public $redirectType;

    /** @var  int|string */
    public $storeId;

    /** @var  null|array */
    public $metadata;

    public function __construct($productId, $requestPath, $targetPath, $redirectType, $storeId, $metadata)
    {
        $this->productId = $productId;
        $this->requestPath = $requestPath;
        $this->targetPath = $targetPath;
        $this->redirectType = $redirectType;
        $this->storeId = $storeId;
        $this->metadata = $metadata;
    }
}