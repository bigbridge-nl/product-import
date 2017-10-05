<?php

namespace BigBridge\ProductImport\Model;

/**
 * @author Patrick van Bergen
 */
class Config
{
    /** @var int The number of products sent to the database at once */
    public $batchSize = 500;
}