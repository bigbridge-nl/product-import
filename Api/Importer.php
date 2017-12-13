<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Resource\ConfigurableStorage;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;
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
        $this->simpleProducts[] = $product;
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
        $this->configurableProducts[] = $product;
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
        $this->simpleStorage->storeSimpleProducts($this->simpleProducts, $this->config, $this->valueSerializer);
        $this->simpleProducts = [];
    }

    private function flushConfigurableProducts()
    {
        $this->configurableStorage->storeConfigurableProducts($this->configurableProducts, $this->config);
        $this->configurableProducts = [];
    }
}