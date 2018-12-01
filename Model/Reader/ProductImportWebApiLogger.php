<?php

namespace BigBridge\ProductImport\Model\Reader;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\ProductImportLogger;
use BigBridge\ProductImport\Api\ProductImportWebApiLoggerInterface;

/**
 * @api
 * @author Patrick van Bergen
 */
class ProductImportWebApiLogger implements ProductImportLogger, ProductImportWebApiLoggerInterface
{
    /** @var string */
    protected $output = "";

    /** @var int */
    protected $failedProductCount = 0;

    /** @var int */
    protected $okProductCount = 0;

    /** @var bool */
    protected $errorOccurred = false;

    /**
     * @param Product $product
     * @return void
     */
    public function productImported(Product $product)
    {
        if ($product->isOk()) {

            $this->okProductCount++;
        } else {
            $this->failedProductCount++;

            foreach ($product->getErrors() as $error) {
                $this->output .= "{$error} for product '{$product->getSku()}' that starts in line {$product->lineNumber}\n";
            }
        }
    }

    /**
     * @param string $e
     * @return void
     */
    public function error(string $e)
    {
        $this->errorOccurred = true;

        $this->output .= "Error: " . $e . "\n";
    }

    /**
     * @param string $info
     * @return void
     */
    public function info(string $info)
    {
        $this->output .= $info . "\n";
    }

    /**
     * @api
     * @return int
     */
    public function getFailedProductCount(): int
    {
        return $this->failedProductCount;
    }

    /**
     * @api
     * @return int
     */
    public function getOkProductCount(): int
    {
        return $this->okProductCount;
    }

    /**
     * @api
     * @return bool
     */
    public function hasErrorOccurred(): bool
    {
        return $this->errorOccurred;
    }

    /**
     * @api
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }
}