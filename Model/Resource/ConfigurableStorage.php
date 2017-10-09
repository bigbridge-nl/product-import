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
    private $db;

    /** @var  Shared */
    private $shared;

    public function __construct(Magento2DbConnection $db, Shared $shared)
    {
        $this->db = $db;
        $this->shared = $shared;
    }

    /**
     * Checks $product for all known requirements.
     *
     * @param ConfigurableProduct $product
     * @return array An array with [ok, error]
     */
    public function validate(ConfigurableProduct $product)
    {
        list($ok, $error) = $this->shared->validate($product);

        return [$ok, $error];
    }

    /**
     * @param ConfigurableProduct[] $configurableProducts
     * @param ImportConfig $config
     */
    public function storeConfigurableProducts(array $configurableProducts, ImportConfig $config)
    {

    }
}