<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\Serialize\JsonValueSerializer;
use BigBridge\ProductImport\Model\Resource\Serialize\SerializeValueSerializer;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * @author Patrick van Bergen
 */
class ImporterFactory
{
    /** @var  MetaData */
    protected $metaData;

    /** @var ProductMetadataInterface */
    private $magentoData;

    public function __construct(MetaData $metaData, ProductMetadataInterface $magentoData)
    {
        $this->metaData = $metaData;

        $this->magentoData = $magentoData;
    }

    /**
     * @param ImportConfig $config
     * @return Importer[] An array of Importer and error message
     */
    public function createImporter(ImportConfig $config)
    {
        $this->fillInDefaults($config);

        $error = $this->validateConfig($config);

        if ($error) {
            $importer = null;
        } else {

            $valueSerializer = $this->createValueSerializer($config);

            $om = ObjectManager::getInstance();
            $importer = $om->create(Importer::class, ['config' => $config, 'valueSerializer' => $valueSerializer]);
        }

        return [$importer, $error];
    }

    protected function fillInDefaults(ImportConfig $config)
    {
        if (is_null($config->magentoVersion)) {
            $config->magentoVersion = $this->magentoData->getVersion();
        }
    }

    protected function createValueSerializer(ImportConfig $config): ValueSerializer
    {
        if (version_compare($config->magentoVersion, '2.2.0') >= 0) {
            return new JsonValueSerializer();
        } else {
            return new SerializeValueSerializer();
        }
    }

    protected function validateConfig(ImportConfig $config)
    {
        $error = "";

        if (!preg_match('/2\..+/', $config->magentoVersion)) {
            $error = "config: invalid Magento version number";
        }

        if (!is_integer($config->batchSize)) {
            $error = "config: batchSize is not an integer";
        } else if ($config->batchSize <= 0) {
            $error = "config: batchSize should be 1 or more";
        } elseif (!is_array($config->resultCallbacks)) {
            $error = "config: resultCallbacks should be an array of functions";
        }

        return $error;
    }
}