<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Resource\MetaData;
use Magento\Framework\App\ObjectManager;

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
     * @param ImportConfig $config
     * @return Importer[] An array of Importer and error message
     */
    public function createImporter(ImportConfig $config)
    {
        $error = $this->validateConfig($config);

        if ($error) {
            $importer = null;
        } else {
            $om = ObjectManager::getInstance();
            $importer = $om->create(Importer::class, ['config' => $config]);
        }

        return [$importer, $error];
    }

    protected function validateConfig(ImportConfig $config)
    {
        $error = "";

        if (!is_integer($config->batchSize)) {
            $error = "config: batchSize is not an integer";
        } else if ($config->batchSize <= 0) {
            $error = "config: batchSize should be 1 or more";
        }

        return $error;
    }
}