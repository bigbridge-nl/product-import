<?php

namespace BigBridge\ProductImport\Model;

/**
 * @author Patrick van Bergen
 */
class ImportConfig
{
    /**
     * @var int The number of products sent to the database at once
     *      If you enlarge the number, make sure the queries do not exceed your maximum query length (max_allowed_packet)
     */
    public $batchSize = 500;

    /**
     * @var string[] An array of the attribute codes of eav attributes that need to be imported
     */
    public $eavAttributes = ['name'];
}