<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
use BigBridge\ProductImport\Model\Resource\ConfigurableStorage;
use BigBridge\ProductImport\Model\Resource\DownloadableStorage;
use BigBridge\ProductImport\Model\Resource\GroupedStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;
use BigBridge\ProductImport\Model\Resource\SimpleStorage;

/**
 * This is the main class for API based imports.
 *
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

    /** @var DownloadableProduct[] */
    protected $downloadableProducts = [];

    /** @var ConfigurableProduct[] */
    protected $configurableProducts = [];

    /** @var GroupedProduct[] */
    protected $groupedProducts = [];

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

    /** @var GroupedStorage */
    protected $groupedStorage;

    /** @var DownloadableStorage */
    protected $downloadableStorage;

    public function __construct(
        ImportConfig $config,
        ValueSerializer $valueSerializer,
        SimpleStorage $simpleStorage,
        ConfigurableStorage $configurableStorage,
        GroupedStorage $groupedStorage,
        ProductEntityStorage $productEntityStorage,
        DownloadableStorage $downloadableStorage)
    {
        $this->config = $config;
        $this->valueSerializer = $valueSerializer;
        $this->simpleStorage = $simpleStorage;
        $this->configurableStorage = $configurableStorage;
        $this->productEntityStorage = $productEntityStorage;
        $this->groupedStorage = $groupedStorage;
        $this->downloadableStorage = $downloadableStorage;
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
     * @param VirtualProduct $product
     * @throws \Exception
     */
    public function importVirtualProduct(VirtualProduct $product)
    {
        $this->importSimpleProduct($product);
    }

    /**
     * @param DownloadableProduct $product
     * @throws \Exception
     */
    public function importDownloadableProduct(DownloadableProduct $product)
    {
        // create placeholders for non-existing linked products
        $this->ensureThatLinkedProductsExist($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->downloadableProducts[$product->getSku()] = $product;

        if (count($this->downloadableProducts) == $this->config->batchSize) {
            $this->flushPlaceholderProducts();
            $this->flushDownloadableProducts();
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
     * @param GroupedProduct $product
     * @throws \Exception
     */
    public function importGroupedProduct(GroupedProduct $product)
    {
        // create placeholders for non-existing member products
        $this->ensureThatMemberProductsExist($product);

        // create placeholders for non-existing linked products
        $this->ensureThatLinkedProductsExist($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->groupedProducts[$product->getSku()] = $product;

        if (count($this->groupedProducts) == $this->config->batchSize) {
            $this->flushPlaceholderProducts();
            $this->flushSimpleProducts();
            $this->flushGroupedProducts();
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
        $this->flushDownloadableProducts();
        $this->flushConfigurableProducts();
        $this->flushGroupedProducts();
    }

    /**
     * @throws \Exception
     */
    protected function flushPlaceholderProducts()
    {
        $this->simpleStorage->storeProducts($this->placeholderProducts, $this->config, $this->valueSerializer, false);
        $this->placeholderProducts = [];
    }

    /**
     * @throws \Exception
     */
    protected function flushSimpleProducts()
    {
        $this->simpleStorage->storeProducts($this->simpleProducts, $this->config, $this->valueSerializer, true);
        $this->simpleProducts = [];
    }

    /**
     * @throws \Exception
     */
    protected function flushDownloadableProducts()
    {
        $this->downloadableStorage->storeProducts($this->downloadableProducts, $this->config, $this->valueSerializer, true);
        $this->downloadableProducts = [];
    }

    /**
     * @throws \Exception
     */
    protected function flushConfigurableProducts()
    {
        $this->configurableStorage->storeProducts($this->configurableProducts, $this->config, $this->valueSerializer, true);
        $this->configurableProducts = [];
    }

    /**
     * @throws \Exception
     */
    protected function flushGroupedProducts()
    {
        $this->groupedStorage->storeProducts($this->groupedProducts, $this->config, $this->valueSerializer, true);
        $this->groupedProducts = [];
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

    /**
     * @param GroupedProduct $product
     * @throws \Exception
     */
    protected function ensureThatMemberProductsExist(GroupedProduct $product)
    {
        // make sure grouped product members exist, by creating placeholders for non-existing linked products
        foreach ($this->createGroupedProductPlaceholders($product) as $placeholder) {
            $this->importPlaceholder($placeholder);
        }
    }

    /**
     * @param GroupedProduct $product
     * @return Product[] An sku indexed array of placeholders
     */
    protected function createGroupedProductPlaceholders(GroupedProduct $product): array
    {
        $memberSkus = [];
        foreach ($product->getMembers() as $member) {
            $memberSkus[] = $member->getSku();
        }

        // quick check if member products were used here at all
        if (empty($memberSkus)) {
            return [];
        }

        $placeholders = [];

        $memberSkus = array_unique($memberSkus);

        $sku2id = $this->productEntityStorage->getExistingSkus($memberSkus);

        foreach ($memberSkus as $sku) {
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