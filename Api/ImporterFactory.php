<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Resource\MetaData;
use Magento\Framework\App\ObjectManager;
use Exception;

/**
 * @author Patrick van Bergen
 */
class ImporterFactory
{
    /** @var  MetaData */
    protected $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    /**
     * Creates an importer based on a given configuration.
     *
     * Note: the config object is copied; making changes to it later does not affect the importer.
     *
     * @param ImportConfig $originalConfig
     * @return Importer
     * @throws Exception
     */
    public function createImporter(ImportConfig $originalConfig)
    {
        // disallow changing the config after import creation; it could cause all kinds of trouble
        $config = clone $originalConfig;

        $this->validateConfig($config);

        $om = ObjectManager::getInstance();
        $importer = $om->create(Importer::class, ['config' => $config]);

        return $importer;
    }

    /**
     * @param ImportConfig $config
     * @throws Exception
     */
    protected function validateConfig(ImportConfig $config)
    {
        if (!is_integer($config->batchSize)) {
            throw new Exception("config: batchSize is not an integer");
        } else if ($config->batchSize <= 0) {
            throw new Exception("config: batchSize should be 1 or more");
        } elseif (!is_array($config->resultCallbacks)) {
            throw new Exception("config: resultCallbacks should be an array of functions");
        }
    }
}