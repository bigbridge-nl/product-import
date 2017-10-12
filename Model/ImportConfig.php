<?php

namespace BigBridge\ProductImport\Model;

/**
 * @author Patrick van Bergen
 */
class ImportConfig
{
    /**
     * @var int The number of products sent to the database at once
     *      The number is a tested optimal balance between speed and database load.
     *      If you enlarge the number, make sure the queries do not exceed your maximum query length (max_allowed_packet)
     */
    public $batchSize = 1000;

    /**
     * @var string[] An array of the attribute codes of eav attributes that need to be imported
     */
    public $eavAttributes = ['name'];
}