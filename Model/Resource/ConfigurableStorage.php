<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\ConfigurableProduct;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;

/**
 * @author Patrick van Bergen
 */
class ConfigurableStorage
{

    /** @var  Magento2DbConnection */
    private $db;

    /** @var  ProductStorage */
    private $shared;

    public function __construct(Magento2DbConnection $db, ProductStorage $shared)
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
        $ok = true;
        $error = "";

        if ($product->sku === null) {
            $ok = false;
            $error = "Missing SKU";
        }

        return [$ok, $error];
    }

    /**
     * @param ConfigurableProduct[] $configurableProducts
     */
    public function storeConfigurableProducts(array $configurableProducts)
    {

    }
}