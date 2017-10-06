<?php

namespace BigBridge\ProductImport\Model;

/**
 * @author Patrick van Bergen
 */
class ImporterFactory
{
    /**
     * @param ImportConfig $config
     * @return Importer
     */
    public function create(ImportConfig $config)
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $importer = $om->create(Importer::class, ['config' => $config]);

        return $importer;
    }
}