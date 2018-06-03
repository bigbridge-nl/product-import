<?php

namespace BigBridge\ProductImport\Api;

use Exception;
use BigBridge\ProductImport\Api\Data\Product;

/**
 * @author Patrick van Bergen
 */
interface ProductImportLogger
{
    /**
     * The callback that is called for each product after it is imported (or failed to import)
     *
     * @param Product $product
     */
    public function productImported(Product $product);

    /**
     * @param Exception $e
     */
    public function handleException(Exception $e);

    /**
     * @param string $info
     */
    public function info(string $info);

    /**
     * Returns the number of failed products
     *
     * @return int
     */
    public function getFailedProductCount(): int;

    /**
     * Returns the number of successful products
     *
     * @return int
     */
    public function getOkProductCount(): int;
}