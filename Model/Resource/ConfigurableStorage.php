<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\ConfigurableProduct;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Api\ImportConfig;

/**
 * @author Patrick van Bergen
 */
class ConfigurableStorage
{

    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $shared;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->shared = $metaData;
    }

    /**
     * @param ConfigurableProduct[] $configurableProducts
     * @param ImportConfig $config
     */
    public function storeConfigurableProducts(array $configurableProducts, ImportConfig $config)
    {

    }
}