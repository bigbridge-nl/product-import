<?php

namespace BigBridge\ProductImport\Api;

use Exception;
use BigBridge\ProductImport\Api\Data\Product;

/**
 * @author Patrick van Bergen
 */
interface ProductImportLogger
{
    public function productImported(Product $product);

    public function handleException(Exception $e);

    public function info(string $info);

    public function getFailedProductCount(): int;

    public function getOkProductCount(): int;
}