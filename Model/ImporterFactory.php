<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class ImporterFactory
{
    /** @var  MetaData */
    private $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    /**
     * @param ImportConfig $config
     * @return Importer[] An array of Importer and error message
     */
    public function create(ImportConfig $config)
    {
        $error = $this->validateConfig($config);

        if ($error) {
            $importer = null;
        } else {
            $om = \Magento\Framework\App\ObjectManager::getInstance();
            $importer = $om->create(Importer::class, ['config' => $config]);
        }

        return [$importer, $error];
    }

    private function validateConfig(ImportConfig $config)
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
                    if (!array_key_exists($eavAttribute, $this->metaData->eavAttributeInfo)) {
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