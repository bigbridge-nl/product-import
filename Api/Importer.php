<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Resource\ConfigurableStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
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
    protected $placeholderProducts = [];

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

    /** @var ProductEntityStorage */
    protected $productEntityStorage;

    public function __construct(
        ImportConfig $config,
        ValueSerializer $valueSerializer,
        SimpleStorage $simpleStorage,
        ConfigurableStorage $configurableStorage,
        ProductEntityStorage $productEntityStorage)
    {
        $this->config = $config;
        $this->valueSerializer = $valueSerializer;
        $this->simpleStorage = $simpleStorage;
        $this->configurableStorage = $configurableStorage;
        $this->productEntityStorage = $productEntityStorage;
    }

    /**
     * @param SimpleProduct $product
     * @throws \Exception
     */
    public function importSimpleProduct(SimpleProduct $product)
    {
        // create placeholders for non-existing linked products
        $this->ensureThatLinkedProductsExist($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->simpleProducts[$product->getSku()] = $product;

        if (count($this->simpleProducts) == $this->config->batchSize) {
            $this->flushPlaceholderProducts();
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

        // create placeholders for non-existing linked products
        $this->ensureThatLinkedProductsExist($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->configurableProducts[$product->getSku()] = $product;

        if (count($this->configurableProducts) == $this->config->batchSize) {
            $this->flushPlaceholderProducts();
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
        $this->flushPlaceholderProducts();
        $this->flushSimpleProducts();
        $this->flushConfigurableProducts();
    }

    /**
     * @throws \Exception
     */
    protected function flushPlaceholderProducts()
    {
        $this->simpleStorage->storeProducts($this->placeholderProducts, $this->config, $this->valueSerializer);
        $this->placeholderProducts = [];
    }

    /**
     * @throws \Exception
     */
    protected function flushSimpleProducts()
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

    /**
     * @param Product $placeholder
     * @throws \Exception
     */
    protected function importPlaceholder(Product $placeholder)
    {
        $this->placeholderProducts[$placeholder->getSku()] = $placeholder;

        if (count($this->placeholderProducts) == $this->config->batchSize) {
            $this->flushPlaceholderProducts();
        }
    }

    /**
     * @param Product $product
     * @throws \Exception
     */
    protected function ensureThatLinkedProductsExist(Product $product)
    {
        // make sure linked products exist, by creating placeholders for non-existing linked products
        foreach ($this->createLinkedProductPlaceholders($product) as $placeholder) {
            $this->importPlaceholder($placeholder);
        }
    }

    /**
     * @param Product $product
     * @return Product[] An sku indexed array of placeholders
     */
    protected function createLinkedProductPlaceholders(Product $product): array
    {
        $linkedSkus = $product->getLinkedProductSkus();

        // quick check if linked products were used here at all
        if (empty($linkedSkus)) {
            return [];
        }

        $placeholders = [];

        // collect all linked product skus
        $allLinkedSkus = [];
        foreach ($linkedSkus as $skuArray) {
            $allLinkedSkus = array_merge($allLinkedSkus, $skuArray);
        }
        $allLinkedSkus = array_unique($allLinkedSkus);

        $sku2id = $this->productEntityStorage->getExistingSkus($allLinkedSkus);

        foreach ($allLinkedSkus as $sku) {
            if (!array_key_exists($sku, $sku2id)) {

                $placeholder = new SimpleProduct($sku);

                $placeholder->global()->setName(Product::PLACEHOLDER_NAME);
                $placeholder->global()->setPrice(Product::PLACEHOLDER_PRICE);
                $placeholder->global()->setStatus(ProductStoreView::STATUS_DISABLED);

                $placeholders[$sku] = $placeholder;
            }
        }

        return $placeholders;
    }
}