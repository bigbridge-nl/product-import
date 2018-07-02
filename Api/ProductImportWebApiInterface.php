<?php

namespace BigBridge\ProductImport\Api;

use Exception;

/**
 * @author Patrick van Bergen
 */
interface ProductImportWebApiInterface
{
    /**
     * Imports products from XML
     *
     * @api
     * @return \BigBridge\ProductImport\Api\ProductImportWebApiLoggerInterface
     * @throws Exception
     */
    public function process();
}