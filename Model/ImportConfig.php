<?php

namespace BigBridge\ProductImport\Model;

/**
 * @author Patrick van Bergen
 */
class ImportConfig
{
    /**
     * When set to true, no database queries are performed
     *
     * @var bool
     */
    public $dryRun = false;

    /**
     * @var int The number of products sent to the database at once
     *      The number is a tested optimal balance between speed and database load.
     *      If you enlarge the number, make sure the queries do not exceed your maximum query length (max_allowed_packet)
     */
    public $batchSize = 1000;

    /**
     * @var callable[]
     *
     * These functions will be called with the result of the import.
     *
     * Function signature:
     *
     * function(BigBridge\ProductImport\Model\Data\Product $product, $ok, $error);
     */
    public $resultCallbacks = [];
}