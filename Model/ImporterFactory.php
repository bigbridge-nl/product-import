<?php

namespace BigBridge\ProductImport\Model;

/**
 * @author Patrick van Bergen
 */
class ImporterFactory
{
    public function create(ImportConfig $config)
    {
        return new Importer($config);
    }
}