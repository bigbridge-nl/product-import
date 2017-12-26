<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Resource\ConfigurableStorage;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;
use BigBridge\ProductImport\Model\Resource\SimpleStorage;

/**
 * This class implements the batch operation.
 * Each batch performs inserts / updates of products of the same type (i.e. all products are either simples or configurables, not a mix of them).
 * For speed it is important that all products in the batch can be treated the same.
 *
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

    /** @var  ValueSerializer */
    protected $valueSerializer;

    /** @var  SimpleStorage */
    protected $simpleStorage;

    /** @var  ConfigurableStorage */
    protected $configurableStorage;

    public function __construct(
        ImportConfig $config,
        ValueSerializer $valueSerializer,
        SimpleStorage $simpleStorage,
        ConfigurableStorage $configurableStorage)
    {
        $this->config = $config;
        $this->valueSerializer = $valueSerializer;
        $this->simpleStorage = $simpleStorage;
        $this->configurableStorage = $configurableStorage;
    }

    /**
     * @param SimpleProduct $product
     * @throws \Exception
     */
    public function importSimpleProduct(SimpleProduct $product)
    {
        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->simpleProducts[$product->getSku()] = $product;

        if (count($this->simpleProducts) == $this->config->batchSize) {
            $this->flushSimpleProducts();
        }
    }

    /**
     * @param ConfigurableProduct $product
     * @throws \Exception
     */
    public function importConfigurableProduct(ConfigurableProduct $product)
    {
        // variants must be done first, their id is needed by the configurable
        foreach ($product->getVariants() as $simple) {
            $this->importSimpleProduct($simple);
        }

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->configurableProducts[$product->getSku()] = $product;

        if (count($this->configurableProducts) == $this->config->batchSize) {
            $this->flushSimpleProducts();
            $this->flushConfigurableProducts();
        }
    }

    /**
     * Call this function only once, at the end of the full import.
     * Not once for every product!
     * @throws \Exception
     */
    public function flush()
    {
        $this->flushSimpleProducts();
        $this->flushConfigurableProducts();
    }

    /**
     * @throws \Exception
     */
    private function flushSimpleProducts()
    {
        $this->simpleStorage->storeProducts($this->simpleProducts, $this->config, $this->valueSerializer);
        $this->simpleProducts = [];
    }

    /**
     * @throws \Exception
     */
    private function flushConfigurableProducts()
    {
        $this->configurableStorage->storeProducts($this->configurableProducts, $this->config, $this->valueSerializer);
        $this->configurableProducts = [];
    }
}