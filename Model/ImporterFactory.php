<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\NameConverter;
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

    /**
     * @return NameConverter
     */
    public function createNameConverter(ImportConfig $config)
    {
        $om = ObjectManager::getInstance();
        $nameConverter = $om->create(NameConverter::class, ['config' => $config]);

        return $nameConverter;
    }

    protected function validateConfig(ImportConfig $config)
    {
        $error = "";

        if (!is_integer($config->batchSize)) {
            $error = "config: batchSize is not an integer";
        } else if ($config->batchSize <= 0) {
            $error = "config: batchSize should be 1 or more";
        }

        if (!is_array($config->eavAttributes)) {

            $error = "config: eavAttributes is not an array";

        } else {

            $notEav = [];

            foreach ($config->eavAttributes as $eavAttribute) {
                if (!is_string($eavAttribute)) {
                    $error = "config: eavAttributes should be strings";
                } else {
                    if (!array_key_exists($eavAttribute, $this->metaData->productEavAttributeInfo)) {
                        $notEav[] = $eavAttribute;
                    }
                }
            }

            if (!empty($notEav)) {
                $error = "config: eavAttributes: not an eav attribute: " . implode(', ', $notEav);
            }
        }

        return $error;
    }
}