<?php

namespace BigBridge\ProductImport\Api;

/**
 * @author Patrick van Bergen
 */
interface ProductImportWebApiLoggerInterface
{
    /**
     * @api
     * @return int
     */
    public function getFailedProductCount(): int;

    /**
     * @api
     * @return int
     */
    public function getOkProductCount(): int;

    /**
     * @api
     * @return bool
     */
    public function hasErrorOccurred(): bool;

    /**
     * @api
     * @return string
     */
    public function getOutput();
}