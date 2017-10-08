<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Data\ConfigurableProduct;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Resource\ConfigurableStorage;
use BigBridge\ProductImport\Model\Resource\SimpleStorage;

/**
 * @author Patrick van Bergen
 */
class Importer
{
    /** @var SimpleProduct[] */
    private $simpleProducts = [];

    /** @var ConfigurableProduct[] */
    private $configurableProducts = [];

    /** @var  ImportConfig */
    private $config;

    /** @var  SimpleStorage */
    private $simpleStorage;

    /** @var  ConfigurableStorage */
    private $configurableStorage;

    public function __construct(ImportConfig $config, SimpleStorage $simpleStorage, ConfigurableStorage $configurableStorage)
    {
        $this->config = $config;
        $this->simpleStorage = $simpleStorage;
        $this->configurableStorage = $configurableStorage;
    }

    /**
     * @param SimpleProduct $product
     */
    public function insert(SimpleProduct $product)
    {
        list($ok, $error) = $this->validate($product);

        if ($ok) {
            $this->simpleProducts[] = $product;
            if (count($this->simpleProducts) == $this->config->batchSize) {
                $this->flushSimpleProducts();
            }
        }

        return [$ok, $error];
    }

    /**
     * Checks $product for all known requirements.
     *
     * @param SimpleProduct $product
     * @return array An array with [ok, error]
     */
    private function validate(SimpleProduct $product)
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
     * @param ConfigurableProduct $product
     */
    public function importConfigurableProduct(ConfigurableProduct $product)
    {
        $this->configurableProducts[] = $product;
        if (count($this->configurableProducts) == $this->config->batchSize) {
            $this->flushSimpleProducts();
            $this->flushConfigurableProducts();
        }
    }

    /**
     * Call this function only once, at the end of the full import.
     * Not once for every product!
     */
    public function flush()
    {
        $this->flushSimpleProducts();
        $this->flushConfigurableProducts();
    }

    private function flushSimpleProducts()
    {
        $this->simpleStorage->storeSimpleProducts($this->simpleProducts);
        $this->simpleProducts = [];
    }

    private function flushConfigurableProducts()
    {
        $this->configurableStorage->storeConfigurableProducts($this->configurableProducts);
        $this->configurableProducts = [];
    }
}