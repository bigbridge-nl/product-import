<?php

namespace BigBridge\ProductImport\Model;

/**
 * @author Patrick van Bergen
 */
class ImportConfig
{
    /** @var int The number of products sent to the database at once */
    public $batchSize = 500;

    public $eavAttributes = ['name'];
}