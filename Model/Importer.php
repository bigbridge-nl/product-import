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
    protected $simpleProducts = [];

    /** @var ConfigurableProduct[] */
    protected $configurableProducts = [];

    /** @var  ImportConfig */
    protected $config;

    /** @var  SimpleStorage */
    protected $simpleStorage;

    /** @var  ConfigurableStorage */
    protected $configurableStorage;

    public function __construct(ImportConfig $config, SimpleStorage $simpleStorage, ConfigurableStorage $configurableStorage)
    {
        $this->config = $config;
        $this->simpleStorage = $simpleStorage;
        $this->configurableStorage = $configurableStorage;

        $this->simpleStorage->setConfig($config);
        $this->configurableStorage->setConfig($config);
    }

    /**
     * @param SimpleProduct $product
     */
    public function insert(SimpleProduct $product)
    {
        $this->simpleProducts[] = $product;
        if (count($this->simpleProducts) == $this->config->batchSize) {
            $this->flushSimpleProducts();
        }
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
        $this->simpleStorage->storeSimpleProducts($this->simpleProducts, $this->config);
        $this->simpleProducts = [];
    }

    private function flushConfigurableProducts()
    {
        $this->configurableStorage->storeConfigurableProducts($this->configurableProducts, $this->config);
        $this->configurableProducts = [];
    }
}