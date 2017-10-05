<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Data\ConfigurableProduct;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Storage\Configurables;
use BigBridge\ProductImport\Model\Storage\Simples;

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

    /** @var  Simples */
    private $simpleStorage;

    /** @var  Configurables */
    private $configurableStorage;

    public function __construct(ImportConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param SimpleProduct $product
     */
    public function importSimpleProduct(SimpleProduct $product)
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
    public function completeFullImport()
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