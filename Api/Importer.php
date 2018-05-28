<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Resource\ProductDeleter;
use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
use BigBridge\ProductImport\Model\Data\Placeholder;
use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\ProductStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;

/**
 * This is the main class for API based imports.
 * It implements the batch operation.
 *
 * @author Patrick van Bergen
 */
class Importer
{
    /** @var Product[] */
    protected $products = [];

    /** @var  ImportConfig */
    protected $config;

    /** @var  ValueSerializer */
    protected $valueSerializer;

    /** @var ProductEntityStorage */
    protected $productEntityStorage;

    /** @var ProductStorage */
    protected $productStorage;

    /** @var ProductDeleter */
    protected $productDeleter;

    /** @var MetaData */
    protected $metaData;

    public function __construct(
        ImportConfig $config,
        ValueSerializer $valueSerializer,
        ProductEntityStorage $productEntityStorage,
        ProductStorage $productStorage,
        ProductDeleter $productDeleter,
        MetaData $metaData)
    {
        $this->config = $config;
        $this->valueSerializer = $valueSerializer;
        $this->productEntityStorage = $productEntityStorage;
        $this->productStorage = $productStorage;
        $this->metaData = $metaData;
        $this->productDeleter = $productDeleter;
    }

    /**
     * @param SimpleProduct $product
     * @throws \Exception
     */
    public function importSimpleProduct(SimpleProduct $product)
    {
        // create placeholders for non-existing linked products
        $this->createLinkedProductPlaceholders($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->products[$product->getSku()] = $product;

        if (count($this->products) >= $this->config->batchSize) {
            $this->flush();
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
        $this->createLinkedProductPlaceholders($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->products[$product->getSku()] = $product;

        if (count($this->products) >= $this->config->batchSize) {
            $this->flush();
        }
    }

    /**
     * @param ConfigurableProduct $product
     * @throws \Exception
     */
    public function importConfigurableProduct(ConfigurableProduct $product)
    {
        // variants must be done first, their id is needed by the configurable
        foreach ($product->getVariants() as $variant) {
            $this->importAnyProduct($variant);
        }

        // create placeholders for non-existing linked products
        $this->createLinkedProductPlaceholders($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->products[$product->getSku()] = $product;

        if (count($this->products) >= $this->config->batchSize) {
            $this->flush();
        }
    }

    /**
     * @param BundleProduct $product
     * @throws \Exception
     */
    public function importBundleProduct(BundleProduct $product)
    {
        // create placeholders for non-existing selection products
        $this->createBundleProductPlaceholders($product);

        // create placeholders for non-existing linked products
        $this->createLinkedProductPlaceholders($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->products[$product->getSku()] = $product;

        if (count($this->products) >= $this->config->batchSize) {
            $this->flush();
        }
    }
    
    /**
     * @param GroupedProduct $product
     * @throws \Exception
     */
    public function importGroupedProduct(GroupedProduct $product)
    {
        // create placeholders for non-existing member products
        $this->createGroupedProductPlaceholders($product);

        // create placeholders for non-existing linked products
        $this->createLinkedProductPlaceholders($product);

        // the sku key is necessary: later products in this batch with the same sku will overwrite former products
        $this->products[$product->getSku()] = $product;

        if (count($this->products) >= $this->config->batchSize) {
            $this->flush();
        }
    }

    /**
     * Call this function only once, at the end of the full import.
     * Not once for every product!
     * @throws \Exception
     */
    public function flush()
    {
        // create child-to-parent links (which causes hard to clean-up cyclic dependencies)
        foreach ($this->products as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                $storeView->parent = $product;
            }
        }

        $this->productStorage->storeProducts($this->products, $this->config, $this->valueSerializer);

        // help the garbage collector by removing cyclic dependencies
        foreach ($this->products as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                $storeView->parent = null;
            }
        }

        $this->products = [];
    }

    /**
     * Delete products specified by id.
     *
     * @param array $ids
     */
    public function deleteProductsByIds(array $ids)
    {
        $this->productDeleter->deleteProductsByIds($ids);
    }

    /**
     * Delete products specified by sku.
     *
     * @param array $skus
     */
    public function deleteProductsBySkus(array $skus)
    {
        $this->productDeleter->deleteProductsBySkus($skus);
    }

    /**
     * @param Product $product
     * @throws \Exception
     */
    public function importAnyProduct(Product $product)
    {
        switch ($product->getType()) {
            case SimpleProduct::TYPE_SIMPLE:
                /** @var SimpleProduct $product */
                $this->importSimpleProduct($product);
                break;
            case VirtualProduct::TYPE_VIRTUAL:
                /** @var VirtualProduct $product */
                $this->importVirtualProduct($product);
                break;
            case DownloadableProduct::TYPE_DOWNLOADABLE:
                /** @var DownloadableProduct $product */
                $this->importDownloadableProduct($product);
                break;
            case ConfigurableProduct::TYPE_CONFIGURABLE:
                /** @var ConfigurableProduct $product */
                $this->importConfigurableProduct($product);
                break;
            case BundleProduct::TYPE_BUNDLE:
                /** @var BundleProduct $product */
                $this->importBundleProduct($product);
                break;
            case GroupedProduct::TYPE_GROUPED:
                /** @var GroupedProduct $product */
                $this->importGroupedProduct($product);
                break;
        }
    }

    /**
     * @param Product $product
     */
    protected function createLinkedProductPlaceholders(Product $product)
    {
        $linkedSkus = $product->getLinkedProductSkus();

        // quick check if linked products were used here at all
        if (empty($linkedSkus)) {
            return;
        }

        // collect all linked product skus
        $allLinkedSkus = [];
        foreach ($linkedSkus as $skuArray) {
            $allLinkedSkus = array_merge($allLinkedSkus, $skuArray);
        }

        $this->createPlaceholders($allLinkedSkus);
    }

    /**
     * @param GroupedProduct $product
     */
    protected function createGroupedProductPlaceholders(GroupedProduct $product)
    {
        $memberSkus = [];
        foreach ($product->getMembers() as $member) {
            $memberSkus[] = $member->getSku();
        }

        $this->createPlaceholders($memberSkus);
    }

    /**
     * @param BundleProduct $product
     */
    protected function createBundleProductPlaceholders(BundleProduct $product)
    {
        $selectionSkus = [];
        if (($options = $product->getOptions()) !== null) {
            foreach ($options as $option) {
                foreach ($option->getSelections() as $selection) {
                    $selectionSkus[] = $selection->getSku();
                }
            }
        }

        $this->createPlaceholders($selectionSkus);
    }

    /**
     * @param array $skus
     */
    protected function createPlaceholders(array $skus)
    {
        if (empty($skus)) {
            return;
        }

        // collect sku's not in this batch
        $absentSkus = [];
        foreach ($skus as $sku) {
            if (!array_key_exists($sku, $this->products)) {
                $absentSkus[] = $sku;
            }
        }

        $sku2id = $this->productEntityStorage->getExistingSkus($absentSkus);

        foreach ($absentSkus as $sku) {
            if (!array_key_exists($sku, $sku2id)) {
                $this->products[$sku] = Placeholder::createPlaceholder($sku, $this->metaData->defaultProductAttributeSetId);
            }
        }
    }
}