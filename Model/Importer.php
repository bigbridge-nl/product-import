<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Data\ConfigurableProduct;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Resource\ConfigurableStorage;
use BigBridge\ProductImport\Model\Resource\SimpleStorage;
use BigBridge\ProductImport\Model\Resource\Validator;

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

    /** @var  Validator */
    protected $validator;

    public function __construct(ImportConfig $config, SimpleStorage $simpleStorage, ConfigurableStorage $configurableStorage, Validator $validator)
    {
        $this->config = $config;
        $this->simpleStorage = $simpleStorage;
        $this->configurableStorage = $configurableStorage;
        $this->validator = $validator;

        $this->simpleStorage->setConfig($config);
        $this->configurableStorage->setConfig($config);
        $this->validator->setConfig($config);
    }

    /**
     * @param SimpleProduct $product
     * @return bool[] An array with [ok, error]
     */
    public function insert(SimpleProduct $product)
    {
        list($ok, $error) = $this->validator->validate($product);

        if ($ok) {
            $this->simpleProducts[] = $product;
            if (count($this->simpleProducts) == $this->config->batchSize) {
                $this->flushSimpleProducts();
            }
        }

        return [$ok, $error];
    }

    /**
     * @param ConfigurableProduct $product
     */
    public function importConfigurableProduct(ConfigurableProduct $product)
    {
        list($ok, $error) = $this->validator->validate($product);

        $this->configurableProducts[] = $product;
        if (count($this->configurableProducts) == $this->config->batchSize) {
            $this->flushSimpleProducts();
            $this->flushConfigurableProducts();
        }

        return [$ok, $error];
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