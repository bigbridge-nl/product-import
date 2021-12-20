<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Resolver\ReferenceResolver;
use BigBridge\ProductImport\Model\Resource\Resolver\UrlKeyGenerator;
use BigBridge\ProductImport\Model\Resource\Storage\BundleStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ConfigurableStorage;
use BigBridge\ProductImport\Model\Resource\Storage\CustomOptionStorage;
use BigBridge\ProductImport\Model\Resource\Storage\DownloadableStorage;
use BigBridge\ProductImport\Model\Resource\Storage\GroupedStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ImageStorage;
use BigBridge\ProductImport\Model\Resource\Storage\LinkedProductStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use BigBridge\ProductImport\Model\Resource\Storage\SourceItemStorage;
use BigBridge\ProductImport\Model\Resource\Storage\StockItemStorage;
use BigBridge\ProductImport\Model\Resource\Storage\TierPriceStorage;
use BigBridge\ProductImport\Model\Resource\Storage\UrlRewriteStorage;
use BigBridge\ProductImport\Model\Resource\Storage\WeeeStorage;
use BigBridge\ProductImport\Model\Resource\ThirdParty\M2EProNotification;
use BigBridge\ProductImport\Model\Resource\Validation\Validator;
use Exception;

/**
 * @author Patrick van Bergen
 */
class ProductStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    /** @var Validator */
    protected $validator;

    /** @var  ReferenceResolver */
    protected $referenceResolver;

    /** @var UrlKeyGenerator */
    protected $urlKeyGenerator;

    /** @var UrlRewriteStorage */
    protected $urlRewriteStorage;

    /** @var ImageStorage */
    protected $imageStorage;

    /** @var LinkedProductStorage */
    protected $linkedProductStorage;

    /** @var ProductEntityStorage */
    protected $productEntityStorage;

    /** @var TierPriceStorage */
    protected $tierPriceStorage;

    /** @var StockItemStorage */
    protected $stockItemStorage;

    /** @var SourceItemStorage */
    protected $sourceItemStorage;

    /** @var CustomOptionStorage */
    protected $customOptionStorage;

    /** @var WeeeStorage */
    protected $weeeStorage;

    /** @var DownloadableStorage */
    protected $downloadableStorage;

    /** @var ConfigurableStorage */
    protected $configurableStorage;

    /** @var BundleStorage */
    protected $bundleStorage;

    /** @var GroupedStorage */
    protected $groupedStorage;

    /** @var ProductTypeChanger */
    protected $productTypeChanger;

    /** @var M2EProNotification */
    protected $m2EProNotification;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        Validator $validator,
        ReferenceResolver $referenceResolver,
        UrlKeyGenerator $urlKeyGenerator,
        UrlRewriteStorage $urlRewriteStorage,
        ProductEntityStorage $productEntityStorage,
        ImageStorage $imageStorage,
        LinkedProductStorage $linkedProductStorage,
        TierPriceStorage $tierPriceStorage,
        StockItemStorage $stockItemStorage,
        SourceItemStorage $sourceItemStorage,
        CustomOptionStorage $customOptionStorage,
        WeeeStorage $weeeStorage,
        DownloadableStorage $downloadableStorage,
        ConfigurableStorage $configurableStorage,
        BundleStorage $bundleStorage,
        GroupedStorage $groupedStorage,
        M2EProNotification $m2EProNotification,
        ProductTypeChanger $productTypeChanger)
    {
        $this->db = $db;
        $this->metaData = $metaData;
        $this->validator = $validator;
        $this->referenceResolver = $referenceResolver;
        $this->urlKeyGenerator = $urlKeyGenerator;
        $this->urlRewriteStorage = $urlRewriteStorage;
        $this->productEntityStorage = $productEntityStorage;
        $this->imageStorage = $imageStorage;
        $this->linkedProductStorage = $linkedProductStorage;
        $this->tierPriceStorage = $tierPriceStorage;
        $this->stockItemStorage = $stockItemStorage;
        $this->sourceItemStorage = $sourceItemStorage;
        $this->customOptionStorage = $customOptionStorage;
        $this->weeeStorage = $weeeStorage;
        $this->downloadableStorage = $downloadableStorage;
        $this->configurableStorage = $configurableStorage;
        $this->bundleStorage = $bundleStorage;
        $this->groupedStorage = $groupedStorage;
        $this->productTypeChanger = $productTypeChanger;
        $this->m2EProNotification = $m2EProNotification;
    }

    /**
     * @param Product[] $products Sku-indexed products of various product types
     * @param ImportConfig $config
     * @throws Exception
     */
    public function storeProducts(array $products, ImportConfig $config)
    {
        if (empty($products)) {
            return;
        }

        // transform and validate products
        $validProducts = $this->preProcessProducts($products, $config);

        // in a "dry run" no actual imports to the database are done
        if (!$config->dryRun) {

            // store the products in the database
            $this->saveProducts($validProducts, $config);
        }

        // call user defined functions to let them process the results
        if (($callback = $config->resultCallback) !== null) {
            foreach ($products as $product) {
                if ($product->usedAsPlaceholder()) {
                    continue;
                }
                call_user_func($callback, $product);
            }
        }
    }

    /**
     * Transforms raw products to fully prepared products.
     *
     * Returns all valid (error-free) products.
     *
     * @param array $products
     * @param ImportConfig $config
     * @return array
     * @throws Exception
     */
    public function preProcessProducts(array $products, ImportConfig $config): array
    {
        // start filtering out products that use features that are not part of this Magento version
        $products = $this->checkForVersionSpecificFeatures($products);

        // check if the pre-specified ids exist; changes $product->errors
        $this->productEntityStorage->checkIfIdsExist($products);

        // distinguish between inserts and updates (and assign ids)
        list(, $updateProducts) = $this->assignProductIds($products);

        // replace reference(s) with ids; changes $product->errors
        $this->referenceResolver->resolveExternalReferences($products, $config);

        // create url keys based on name and id; changes $product->errors
        $this->urlKeyGenerator->resolveAndValidateUrlKeys($products, $config->urlKeyScheme, $config->duplicateUrlKeyStrategy);

        // check for products that changed type; changes $product->errors
        $this->productTypeChanger->checkTypeChanges($updateProducts, $config);

        // collect all images in temporary local directory; changes $product->errors
        $this->imageStorage->moveImagesToTemporaryLocation($products, $config);

        $almostValidProducts = [];
        foreach ($products as $product) {

            // checks all attributes, changes $product->errors
            $this->validator->validate($product);

            if ($product->isOk()) {
                $almostValidProducts[] = $product;
            }
        }

        // compound product specific validation (must be done after all members have been evaluated)
        $validProducts = [];
        foreach ($almostValidProducts as $product) {
            if (!($product instanceof SimpleProduct)) {
                $this->validator->validateCompound($product, $products);
                if (!$product->isOk()) {
                    continue;
                }
            }
            $validProducts[] = $product;
        }

        return $validProducts;
    }

    /**
     * @param Product[] $products
     */
    protected function checkForVersionSpecificFeatures(array $products): array
    {
        $validProducts = [];

        if (version_compare($this->metaData->magentoVersion, "2.3.0") < 0) {

            foreach ($products as $product) {
                if (!empty($product->getSourceItems())) {
                    $product->addError("source items are supported only in Magento 2.3");
                } else {
                    $validProducts[] = $product;
                }
            }
        } else {
            $validProducts = $products;
        }

        return $validProducts;
    }

    /**
     * @param Product[] $products
     * @return array
     */
    protected function assignProductIds(array $products)
    {
        // find existing products ids from their skus
        $sku2id = $this->productEntityStorage->getExistingProductIds($products);

        // separate new products from existing products
        $insertProducts = $updateProducts = [];
        foreach ($products as $product) {

            if ($product->id) {
                $updateProducts[] = $product;
            } else if (array_key_exists($product->getSku(), $sku2id)) {
                $product->id = $sku2id[$product->getSku()];
                $updateProducts[] = $product;
            } else {
                $insertProducts[] = $product;
            }
        }

        return [$insertProducts, $updateProducts];
    }

    /**
     * @param Product[] $validProducts
     * @param ImportConfig $config
     * @throws Exception
     */
    protected function saveProducts(array $validProducts, ImportConfig $config)
    {
        $validUpdateProducts = [];
        $validInsertProducts = [];

        $productsByType = [
            DownloadableProduct::TYPE_DOWNLOADABLE => [],
            GroupedProduct::TYPE_GROUPED => [],
            BundleProduct::TYPE_BUNDLE => [],
            ConfigurableProduct::TYPE_CONFIGURABLE => [],
        ];

        foreach ($validProducts as $product) {

            // collect by type
            $productsByType[$product->getType()][] = $product;

            // collect valid new and existing products
            if ($product->id !== null) {
                $validUpdateProducts[] = $product;
            } else {
                $validInsertProducts[] = $product;
            }
        }

        // start by removing old structures and setting attributes (virtual!)
        $this->productTypeChanger->performTypeChanges($validUpdateProducts);

        list($upsertAttributes, $deleteAttributes) = $this->separateUpsertsFromDeletes($validProducts, $config);

        $this->productEntityStorage->insertMainTable($validInsertProducts);
        $this->productEntityStorage->updateMainTable($validUpdateProducts);

        $this->referenceResolver->resolveProductReferences($validProducts);

        $this->productEntityStorage->removeUrlPaths($validProducts);

        foreach ($deleteAttributes as $eavAttribute => $storeViews) {
            $this->productEntityStorage->removeEavAttribute($storeViews, $eavAttribute);
        }

        foreach ($upsertAttributes as $eavAttribute => $storeViews) {
            $this->productEntityStorage->insertEavAttribute($storeViews, $eavAttribute);
        }

        $this->customOptionStorage->updateCustomOptions($validProducts);
        $this->weeeStorage->updateWeees($validProducts);
        if ($config->categoryStrategy === ImportConfig::CATEGORY_STRATEGY_SET) {
            $this->productEntityStorage->removeOldCategoryIds($validProducts);
        }
        $this->productEntityStorage->insertCategoryIds($validProducts);
        if ($config->websiteStrategy === ImportConfig::WEBSITE_STRATEGY_SET) {
            $this->productEntityStorage->removeOldWebsiteIds($validProducts);
        }
        $this->productEntityStorage->insertWebsiteIds($validProducts);
        $this->stockItemStorage->storeStockItems($validProducts);
        $this->linkedProductStorage->updateLinkedProducts($validProducts);
        $this->imageStorage->storeProductImages($validProducts,
            $config->imageStrategy === ImportConfig::IMAGE_STRATEGY_SET,
            $config->existingImageStrategy == ImportConfig::EXISTING_IMAGE_STRATEGY_FORCE_DOWNLOAD);
        $this->tierPriceStorage->updateTierPrices($validProducts);

        // url_rewrite (must be done after url_key and category_id)
        $this->urlRewriteStorage->updateRewrites($validProducts,
            $config->handleRedirects === ImportConfig::KEEP_REDIRECTS,
            $config->handleCategoryRewrites === ImportConfig::KEEP_CATEGORY_REWRITES);

        $this->downloadableStorage->performTypeSpecificStorage($productsByType[DownloadableProduct::TYPE_DOWNLOADABLE]);
        $this->groupedStorage->performTypeSpecificStorage($productsByType[GroupedProduct::TYPE_GROUPED]);
        $this->bundleStorage->performTypeSpecificStorage($productsByType[BundleProduct::TYPE_BUNDLE]);
        $this->configurableStorage->performTypeSpecificStorage($productsByType[ConfigurableProduct::TYPE_CONFIGURABLE]);

        if (version_compare($this->metaData->magentoVersion, "2.3.0") >= 0) {
            $this->sourceItemStorage->storeSourceItems($validProducts);
        }

        if ($config->M2EPro === ImportConfig::M2EPRO_YES) {
            $this->m2EProNotification->notify($validProducts);
        }
    }

    /**
     * @param ImportConfig $config
     * @param Product[] $products
     * @return array
     */
    protected function separateUpsertsFromDeletes(array $products, ImportConfig $config): array
    {
        $attributeInfo = $this->metaData->productEavAttributeInfo;

        $upsertAttributes = [];
        $deleteAttributes = [];

        foreach ($products as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                foreach ($storeView->getAttributes() as $key => $value) {

                    if ($value === null) {
                        $deleteAttributes[$key][] = $storeView;
                    } elseif ($value === "") {

                        if ($attributeInfo[$key]->isTextual()) {
                            if ($config->emptyTextValueStrategy === ImportConfig::EMPTY_TEXTUAL_VALUE_STRATEGY_REMOVE) {
                                $deleteAttributes[$key][] = $storeView;
                                continue;
                            } else {
                                continue;
                            }
                        } else {
                            if ($config->emptyNonTextValueStrategy === ImportConfig::EMPTY_NONTEXTUAL_VALUE_STRATEGY_REMOVE) {
                                $deleteAttributes[$key][] = $storeView;
                                continue;
                            } else {
                                continue;
                            }
                        }

                    } else {
                        // a non-empty value
                        $upsertAttributes[$key][] = $storeView;
                    }
                }
            }
        }
        return array($upsertAttributes, $deleteAttributes);
    }
}
