<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\BundleProductStoreView;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\DownloadableProductStoreView;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Resolver\ReferenceResolver;
use BigBridge\ProductImport\Model\Resource\Resolver\UrlKeyGenerator;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;
use BigBridge\ProductImport\Model\Resource\Storage\BundleStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ConfigurableStorage;
use BigBridge\ProductImport\Model\Resource\Storage\CustomOptionStorage;
use BigBridge\ProductImport\Model\Resource\Storage\DownloadableStorage;
use BigBridge\ProductImport\Model\Resource\Storage\GroupedStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ImageStorage;
use BigBridge\ProductImport\Model\Resource\Storage\LinkedProductStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use BigBridge\ProductImport\Model\Resource\Storage\StockItemStorage;
use BigBridge\ProductImport\Model\Resource\Storage\TierPriceStorage;
use BigBridge\ProductImport\Model\Resource\Storage\UrlRewriteStorage;
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

    /** @var CustomOptionStorage */
    protected $customOptionStorage;

    /** @var DownloadableStorage */
    protected $downloadableStorage;

    /** @var ConfigurableStorage */
    protected $configurableStorage;

    /** @var BundleStorage */
    protected $bundleStorage;

    /** @var GroupedStorage */
    protected $groupedStorage;

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
        CustomOptionStorage $customOptionStorage,
        DownloadableStorage $downloadableStorage,
        ConfigurableStorage $configurableStorage,
        BundleStorage $bundleStorage,
        GroupedStorage $groupedStorage
)
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
        $this->customOptionStorage = $customOptionStorage;
        $this->downloadableStorage = $downloadableStorage;
        $this->configurableStorage = $configurableStorage;
        $this->bundleStorage = $bundleStorage;
        $this->groupedStorage = $groupedStorage;
    }

    /**
     * @param Product[] $products Sku-indexed products of various product types
     * @param ImportConfig $config
     * @param ValueSerializer $valueSerializer
     * @param bool $useCallbacks
     * @throws Exception
     */
    public function storeProducts(array $products, ImportConfig $config, ValueSerializer $valueSerializer, bool $useCallbacks)
    {
        if (empty($products)) {
            return;
        }

        // connect store view to product
        $this->setupStoreViewWiring($products);

        // collect skus
        $skus = [];
        foreach ($products as $product) {
            $skus[] = $product->getSku();
        }

        // find existing products ids from their skus
        $sku2id = $this->productEntityStorage->getExistingSkus($skus);

        // separate new products from existing products and assign id
        $insertProducts = $updateProducts = $productsWithId = [];
        foreach ($products as $product) {

            if ($product->id) {
                $updateProducts[] = $product;
                $productsWithId[] = $product;
            } else if (array_key_exists($product->getSku(), $sku2id)) {
                $product->id = $sku2id[$product->getSku()];
                $updateProducts[] = $product;
            } else {
                $insertProducts[] = $product;
            }
        }

        // check if the pre-specified ids exist
        $this->productEntityStorage->checkIfIdsExist($productsWithId);

        // set default values for new products
        $this->setDefaultValues($insertProducts);

        // replace reference(s) with ids, changes $product->errors
        $this->referenceResolver->resolveExternalReferences($products, $config);

        // create url keys based on name and id
        // changes $product->errors
        $this->urlKeyGenerator->createUrlKeysForNewProducts($insertProducts, $config->urlKeyScheme, $config->duplicateUrlKeyStrategy);
        $this->urlKeyGenerator->createUrlKeysForExistingProducts($updateProducts, $config->urlKeyScheme, $config->duplicateUrlKeyStrategy);

        // create an array of products without errors
        $validProducts = $this->collectValidProducts($products, $config);

        // in a "dry run" no actual imports to the database are done
        if (!$config->dryRun) {

            // store the products in the database
            $this->saveProducts($validProducts, $valueSerializer, $config);
        }

        // call user defined functions to let them process the results
        if ($useCallbacks) {
            foreach ($config->resultCallbacks as $callback) {
                foreach ($products as $product) {
                    call_user_func($callback, $product);
                }
            }
        }

        // disconnect store view to product
        // this is done to remove reference cycles that trouble garbage collection
        $this->tearDownStoreViewWiring($products);
    }

    /**
     * Connect product to store view
     *
     * @param Product[] $products
     */
    protected function setupStoreViewWiring(array $products)
    {
        foreach ($products as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                $storeView->parent = $product;
            }
        }
    }

    /**
     * Help the garbage collector by removing cyclic dependencies
     *
     * @param Product[] $products
     */
    protected function tearDownStoreViewWiring(array $products)
    {
        foreach ($products as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                $storeView->parent = null;
            }
        }
    }

    /**
     * @param array $products
     * @param ImportConfig $config
     * @return array
     */
    public function collectValidProducts(array $products, ImportConfig $config): array
    {
        $validProducts = [];

        foreach ($products as $product) {

            $this->imageStorage->moveImagesToTemporaryLocation($product, $config);

            // checks all attributes, changes $product->errors
            $this->validator->validate($product);

            if (!$product->isOk()) {
                continue;
            }

            // collect valid products
            $validProducts[] = $product;
        }
        return $validProducts;
    }

    /**
     * @param Product[] $insertProducts
     */
    protected function setDefaultValues(array $insertProducts)
    {
        foreach ($insertProducts as $product) {

            $global = $product->global();
            $attributes = $global->getAttributes();
            $unresolvedProductAttributes = $product->getUnresolvedAttributes();
            $unresolvedGlobalAttributes = $global->getUnresolvedAttributes();

            // attribute set: Default
            if ($product->getAttributeSetId() === null &&
                !array_key_exists(Product::ATTRIBUTE_SET_ID, $unresolvedProductAttributes)) {
                $product->setAttributeSetByName("Default");
            }

            // visibility: both
            if (!array_key_exists(ProductStoreView::ATTR_VISIBILITY, $attributes)) {
                $global->setVisibility(ProductStoreView::VISIBILITY_BOTH);
            }
            // status: disabled
            if (!array_key_exists(ProductStoreView::ATTR_STATUS, $attributes)) {
                $global->setStatus(ProductStoreView::STATUS_DISABLED);
            }
            // tax class: Taxable Goods
            if (!array_key_exists(ProductStoreView::ATTR_TAX_CLASS_ID, $attributes) &&
                !array_key_exists(ProductStoreView::ATTR_TAX_CLASS_ID, $unresolvedGlobalAttributes)) {
                $global->setTaxClassName("Taxable Goods");
            }

            if ($global instanceof DownloadableProductStoreView) {
                if (!array_key_exists(DownloadableProductStoreView::ATTR_LINKS_PURCHASED_SEPARATELY, $attributes)) {
                    $global->setLinksPurchasedSeparately(false);
                }
                if (!array_key_exists(DownloadableProductStoreView::ATTR_LINKS_TITLE, $attributes)) {
                    $global->setLinksTitle("Links");
                }
                if (!array_key_exists(DownloadableProductStoreView::ATTR_SAMPLES_TITLE, $attributes)) {
                    $global->setSamplesTitle("Samples");
                }
            } elseif ($global instanceof BundleProductStoreView) {
                if (!array_key_exists(BundleProductStoreView::ATTR_PRICE_TYPE, $attributes)) {
                    $global->setPriceType(BundleProductStoreView::PRICE_TYPE_DYNAMIC);
                }
                if (!array_key_exists(BundleProductStoreView::ATTR_PRICE_VIEW, $attributes)) {
                    $global->setPriceView(BundleProductStoreView::PRICE_VIEW_PRICE_RANGE);
                }
                if (!array_key_exists(BundleProductStoreView::ATTR_SKU_TYPE, $attributes)) {
                    $global->setSkuType(BundleProductStoreView::SKU_TYPE_DYNAMIC);
                }
                if (!array_key_exists(BundleProductStoreView::ATTR_WEIGHT_TYPE, $attributes)) {
                    $global->setWeightType(BundleProductStoreView::WEIGHT_TYPE_DYNAMIC);
                }
                if (!array_key_exists(BundleProductStoreView::ATTR_SHIPMENT_TYPE, $attributes)) {
                    $global->setShipmentType(BundleProductStoreView::SHIPMENT_TYPE_TOGETHER);
                }
            }
        }
    }

    /**
     * @param Product[] $validProducts
     * @param ValueSerializer $valueSerializer
     * @param ImportConfig $config
     * @throws Exception
     */
    protected function saveProducts(array $validProducts, ValueSerializer $valueSerializer, ImportConfig $config)
    {
        $validUpdateProducts = $validInsertProducts = [];
        $productsByAttribute = [];
        $productsWithCategories = [];
        $productsWithWebsites = [];
        $productsWithOptions = [];
        $productsWithStockItems = [];

        $productsByType = [
            DownloadableProduct::TYPE_DOWNLOADABLE => [],
            GroupedProduct::TYPE_GROUPED => [],
            BundleProduct::TYPE_BUNDLE => [],
            ConfigurableProduct::TYPE_CONFIGURABLE => [],
        ];

        // collect products by aspect that needs to be updated
        foreach ($validProducts as $product) {

            $productsByType[$product->getType()][] = $product;

            if ($product->getCustomOptions() !== null) {
                $productsWithOptions[] = $product;
            }

            // collect valid new and existing products
            if ($product->id !== null) {
                $validUpdateProducts[] = $product;
            } else {
                $validInsertProducts[] = $product;
            }

            if ($product->getCategoryIds() !== []) {
                $productsWithCategories[] = $product;
            }

            if ($product->getWebsiteIds() !== []) {
                $productsWithWebsites[] = $product;
            }

            if ($product->getStockItems() !== []) {
                $productsWithStockItems[] = $product;
            }

            foreach ($product->getStoreViews() as $storeView) {
                foreach ($storeView->getAttributes() as $key => $value) {
                    $productsByAttribute[$key][] = $storeView;
                }
            }
        }

        $this->db->execute("START TRANSACTION");

        // existing values must be queried before the product is inserted or updated
        $existingValues = $this->getExistingProductValues($validUpdateProducts);

        try {

            $this->productEntityStorage->insertMainTable($validInsertProducts);
            $this->productEntityStorage->updateMainTable($validUpdateProducts);

            $this->referenceResolver->resolveProductReferences($validProducts, $config);

            foreach ($productsByAttribute as $eavAttribute => $products) {
                $this->productEntityStorage->insertEavAttribute($products, $eavAttribute);
            }

            $this->customOptionStorage->updateCustomOptions($productsWithOptions);
            $this->productEntityStorage->insertCategoryIds($productsWithCategories);
            $this->productEntityStorage->insertWebsiteIds($productsWithWebsites);
            $this->stockItemStorage->storeStockItems($productsWithStockItems);
            $this->linkedProductStorage->updateLinkedProducts($validProducts);
            $this->imageStorage->storeProductImages($validProducts);
            $this->tierPriceStorage->updateTierPrices($validProducts);

            // url_rewrite (must be done after url_key and category_id)
            $this->urlRewriteStorage->updateRewrites($validProducts, $existingValues, $valueSerializer);

            $this->downloadableStorage->performTypeSpecificStorage($productsByType[DownloadableProduct::TYPE_DOWNLOADABLE]);
            $this->groupedStorage->performTypeSpecificStorage($productsByType[GroupedProduct::TYPE_GROUPED]);
            $this->bundleStorage->performTypeSpecificStorage($productsByType[BundleProduct::TYPE_BUNDLE]);
            $this->configurableStorage->performTypeSpecificStorage($productsByType[ConfigurableProduct::TYPE_CONFIGURABLE]);

            $this->db->execute("COMMIT");

        } catch (Exception $e) {

            // rollback the transaction
            try { $this->db->execute("ROLLBACK"); } catch (Exception $f) {}

            // let the application handle the exception
            throw $e;
        }
    }

    protected function getExistingProductValues(array $products)
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_column($products, 'id');

        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        $existingData = $this->db->fetchAllAssoc("
            SELECT URL_KEY.`entity_id` as product_id, URL_KEY.`value` AS url_key, GROUP_CONCAT(PG.`category_id` SEPARATOR ',') as category_ids, URL_KEY.`store_id`
            FROM `{$this->metaData->productEntityTable}_varchar` URL_KEY
            LEFT JOIN `{$this->metaData->urlRewriteProductCategoryTable}` PG ON PG.`product_id` = URL_KEY.`entity_id`
            WHERE 
                URL_KEY.`attribute_id` = ? AND
                URL_KEY.`entity_id` IN (" . $this->db->getMarks($productIds) . ")
            GROUP BY URL_KEY.`entity_id`, URL_KEY.`store_id` 
        ", array_merge([
            $attributeId
        ], $productIds));

        $data = [];
        foreach ($existingData as $existingDatum) {
            $productId = $existingDatum['product_id'];
            $storeId = $existingDatum['store_id'];
            $categoryIds = is_null($existingDatum['category_ids']) ? [] : explode(',', $existingDatum['category_ids']);
            $urlKey = $existingDatum['url_key'];
            $data[$storeId][$productId] = ['url_key' => $urlKey, 'category_ids' => $categoryIds];
        }

        return $data;
    }
}