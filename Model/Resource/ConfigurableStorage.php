<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\ConfigurableProduct;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\ImportConfig;

/**
 * @author Patrick van Bergen
 */
class ConfigurableStorage
{

    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $shared;

    /** @var  ImportConfig */
    protected $config;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->shared = $metaData;
    }

    public function setConfig(ImportConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param ConfigurableProduct[] $configurableProducts
     * @param ImportConfig $config
     */
    public function storeConfigurableProducts(array $configurableProducts, ImportConfig $config)
    {

    }
}