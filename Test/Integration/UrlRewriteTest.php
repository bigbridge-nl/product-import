<?php

namespace BigBridge\ProductImport\Test\Integration;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Model\Resource\Resolver\CategoryImporter;
use BigBridge\ProductImport\Model\Resource\Serialize\JsonValueSerializer;
use BigBridge\ProductImport\Model\Resource\Serialize\SerializeValueSerializer;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\ImporterFactory;

/**
 * @author Patrick van Bergen
 */
class UrlRewriteTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /** @var  ImporterFactory */
    private static $factory;

    /** @var ProductRepositoryInterface $repository */
    private static $repository;

    /** @var  Magento2DbConnection */
    protected static $db;

    /** @var  Metadata */
    protected $metadata;

    public static function setUpBeforeClass(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);

        /** @var ProductRepositoryInterface $repository */
        self::$repository = $objectManager->get(ProductRepositoryInterface::class);

        /** @var Magento2DbConnection $db */
        self::$db = $objectManager->get(Magento2DbConnection::class);

        $metadata = $objectManager->get(MetaData::class);

        $table = $metadata->productEntityTable;
        self::$db->execute("DELETE FROM `{$table}` WHERE sku LIKE '%-product-import'");
        $table = $metadata->urlRewriteTable;
        self::$db->execute("DELETE FROM `{$table}` WHERE request_path LIKE '%product-import.html'");
    }

    public function __construct(?string $name = null, array $data = array(), string $dataName = '')
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->metadata = $objectManager->get(MetaData::class);

        parent::__construct($name, $data, $dataName);
    }

    /**
     * @throws Exception
     */
    public function testUrlRewriteCompoundCategories()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->metadata = $objectManager->get(MetaData::class);

        /** @var Magento2DbConnection $db */
        $db = $objectManager->get(Magento2DbConnection::class);
        /** @var CategoryImporter $categoryImporter */
        $categoryImporter = $objectManager->get(CategoryImporter::class);
        list($c1,) = $categoryImporter->importCategoryPath("Default Category/Boxes", true, '/', ImportConfig::CATEGORY_URL_SEGMENTED);
        list($c2a,) = $categoryImporter->importCategoryPath("Default Category/Colored Things", true, '/', ImportConfig::CATEGORY_URL_SEGMENTED);
        list($c2b,) = $categoryImporter->importCategoryPath("Default Category/Colored Things/Containers", true, '/', ImportConfig::CATEGORY_URL_SEGMENTED);
        list($c2c,) = $categoryImporter->importCategoryPath("Default Category/Colored Things/Containers/Large", true, '/', ImportConfig::CATEGORY_URL_FLAT);

        foreach ([$c2b => 'colored-things/containers', $c2c => 'large'] as $id => $urlPath) {
            $path = self::$db->fetchSingleCell("
            SELECT value FROM catalog_category_entity_varchar
            WHERE store_id = 0 AND entity_id = :cat AND attribute_id =
                (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'url_path' AND entity_type_id = 3)
        ", [
                'cat' => $id
            ]);

            $this->assertEquals($urlPath, $path);
        }

        $config = new ImportConfig();
        $this->metadata->valueSerializer = new SerializeValueSerializer();

        $importer = self::$factory->createImporter($config);

        $sku1 = uniqid("bb");
        $urlKey = 'u' . $sku1;

        $product = new SimpleProduct($sku1);

        $product->setAttributeSetByName("Default");
        $product->addCategoriesByGlobalName(["Default Category/Boxes", "Default Category/Colored Things/Containers/Large", "Default Category/Colored Things/Containers"]);

        // give this category a Dutch name
        $urlKeyAttributeId = $this->metadata->categoryAttributeMap['url_key'];

        self::$db->execute("
            INSERT IGNORE INTO `{$this->metadata->categoryEntityTable}_varchar`
            SET entity_id = ?, store_id = ?, attribute_id = ?, value = ?
            ", [
            $c1,
            1,
            $urlKeyAttributeId,
            'dozen'
        ]);

        $importer->getCacheManager()->clearCategoryCache();

        $global = $product->global();
        $global->setName("Big Purple Box");
        $global->setPrice("1.25");
        $global->setUrlKey($urlKey);
        $global->setVisibility(ProductStoreView::VISIBILITY_BOTH);

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $product->getErrors());

        $actual = $db->fetchAllNonAssoc("
            SELECT `entity_type`, `request_path`, `target_path`, `redirect_type`, `store_id`, `is_autogenerated`, `metadata` FROM `{$this->metadata->urlRewriteTable}`
            WHERE `store_id` = 1 AND `entity_id` = {$product->id}
            ORDER BY `url_rewrite_id`
        ");

        $expected = [
            ['product', $urlKey . '.html', 'catalog/product/view/id/' . $product->id, '0', '1', '1', null],
            ['product', 'dozen/' . $urlKey . '.html', 'catalog/product/view/id/' . $product->id . '/category/' . $c1, '0', '1', '1', serialize(['category_id' => (string)$c1])],
            ['product', 'colored-things/' . $urlKey . '.html', 'catalog/product/view/id/' . $product->id . '/category/' . $c2a, '0', '1', '1', serialize(['category_id' => (string)$c2a])],
            ['product', 'colored-things/containers/' . $urlKey . '.html', 'catalog/product/view/id/' . $product->id . '/category/' . $c2b, '0', '1', '1', serialize(['category_id' => (string)$c2b])],
            ['product', 'colored-things/containers/large/' . $urlKey . '.html', 'catalog/product/view/id/' . $product->id . '/category/' . $c2c, '0', '1', '1', serialize(['category_id' => (string)$c2c])],
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * @throws Exception
     */
    public function testNoUrlRewrites()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->metadata = $objectManager->get(MetaData::class);

        /** @var Magento2DbConnection $db */
        $db = $objectManager->get(Magento2DbConnection::class);
        /** @var MetaData $metadata */
        $metadata = $objectManager->get(MetaData::class);

        $config = new ImportConfig();
        $this->metadata->valueSerializer = new SerializeValueSerializer();

        $importer = self::$factory->createImporter($config);

        $sku1 = uniqid("bb");
        $urlKey = 'u' . $sku1;

        $product = new SimpleProduct($sku1);
        $product->setAttributeSetByName("Default");

        $global = $product->global();
        $global->setName("Big Purple Box");
        $global->setPrice("1.25");
        $global->setUrlKey($urlKey);
        // note: no visibility

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $product->getErrors());

        $actual = $db->fetchAllNonAssoc("
            SELECT * FROM `{$metadata->urlRewriteTable}`
            WHERE `store_id` = 1 AND `entity_id` = {$product->id}
            ORDER BY `url_rewrite_id`
        ");

        $expected = [];

        $this->assertEquals($expected, $actual);

        $product = new SimpleProduct($sku1);
        $product->setAttributeSetByName("Default");

        $global = $product->global();
        $global->setName("Big Purple Box");
        $global->setPrice("1.25");
        $global->setUrlKey($urlKey);
        // note: visibility: not visible individually
        $global->setVisibility(ProductStoreView::VISIBILITY_NOT_VISIBLE);

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $product->getErrors());

        $actual = $db->fetchAllNonAssoc("
            SELECT * FROM `{$metadata->urlRewriteTable}`
            WHERE `store_id` = 1 AND `entity_id` = {$product->id}
            ORDER BY `url_rewrite_id`
        ");

        $expected = [];

        $this->assertEquals($expected, $actual);
    }

    /**
     * @throws Exception
     */
    public function testUrlRewritesGeneration()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->metadata = $objectManager->get(MetaData::class);

        $config = new ImportConfig();
        $this->metadata->valueSerializer = new SerializeValueSerializer();

        $importer = self::$factory->createImporter($config);

        // create a product just for the category
        $productX = new SimpleProduct('cat-product-import');
        $productX->setAttributeSetByName("Default");
        $productX->addCategoriesByGlobalName(["Default Category/Boxes"]);
        $productX->global()->setName("Category dummy");
        $productX->global()->setPrice("0");
        $importer->importSimpleProduct($productX);
        $importer->flush();

        $categoryId = $productX->getCategoryIds()[0];

        // give this category a Dutch name
        $urlKeyAttributeId = $this->metadata->categoryAttributeMap['url_key'];

        self::$db->execute("
        INSERT IGNORE INTO `{$this->metadata->categoryEntityTable}_varchar`
        SET entity_id = ?, store_id = ?, attribute_id = ?, value = ?
        ", [
            $categoryId,
            1,
            $urlKeyAttributeId,
            'dozen'
        ]);

        // product
        $product1 = new SimpleProduct('1-product-import');
        $product1->setAttributeSetByName("Default");
        $product1->addCategoriesByGlobalName(["Default Category/Boxes"]);
        $product1->global()->setName("Big Turquoise Box product-import");
        $product1->global()->setPrice("2.75");
        $product1->global()->setVisibility(ProductStoreView::VISIBILITY_IN_CATALOG);
        $product1->global()->generateUrlKey();

        // same sku, different store view
        $default = $product1->storeView('default');
        $default->setName("Grote Turquoise Doos product-import");
        $default->generateUrlKey();

        $importer->importSimpleProduct($product1);

        // another product
        $product3 = new SimpleProduct('2-product-import');
        $product3->setAttributeSetByName("Default");
        $product3->addCategoriesByGlobalName(["Default Category/Boxes"]);
        $product3->global()->setName("Big Grass Green Box product-import");
        $product3->global()->setPrice("2.65");
        $product3->global()->setVisibility(ProductStoreView::VISIBILITY_IN_CATALOG);
        $product3->global()->generateUrlKey();

        $importer->importSimpleProduct($product3);

        $importer->flush();

        // insert

        $expectedRewrites = [
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "dozen/grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],
            ["product", "dozen/big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
        ];

        $expectedIndexes = [
            [(string)$categoryId, $product1->id],
            [(string)$categoryId, $product3->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);

        // store again, with no changes

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);

        // change url_key

        $product3->global()->setUrlKey("a-" . $product3->global()->getUrlKey());

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $expectedRewrites = [
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "dozen/grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],
            ["product", "big-grass-green-box-product-import.html", "a-big-grass-green-box-product-import.html", "301", "1", "0", serialize([])],

            ["product", "dozen/a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
            ["product", "dozen/big-grass-green-box-product-import.html", "dozen/a-big-grass-green-box-product-import.html", "301", "1", "0",
                serialize(['category_id' => (string)$categoryId])],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);

        // change categories

        $product3->addCategoriesByGlobalName(["Default Category/Containers"]);

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $newCategoryId = $product3->getCategoryIds()[0];

        $expectedRewrites = [
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "dozen/grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],
            ["product", "big-grass-green-box-product-import.html", "a-big-grass-green-box-product-import.html", "301", "1", "0", serialize([])],

            ["product", "dozen/a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
            ["product", "dozen/big-grass-green-box-product-import.html", "dozen/a-big-grass-green-box-product-import.html", "301", "1", "0",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "containers/a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$newCategoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$newCategoryId])],
        ];

        $expectedIndexes = [
            [(string)$categoryId, $product1->id],
            [(string)$categoryId, $product3->id],
            [(string)$newCategoryId, $product3->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);

        // remove a category

        self::$db->execute("
            DELETE FROM " . $this->metadata->categoryProductTable . "
            WHERE product_id IN (" . $product1->id . ',' . $product3->id . ")
                AND category_id = " . $categoryId . "
        ");

        $product1->addCategoriesByGlobalName([]);
        $product3->addCategoriesByGlobalName([]);

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $expectedRewrites = [
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],

            ["product", "a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],

            ["product", "big-grass-green-box-product-import.html", "a-big-grass-green-box-product-import.html", "301", "1", "0", serialize([])],

            ["product", "containers/a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$newCategoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$newCategoryId])],
        ];

        $expectedIndexes = [
            [(string)$newCategoryId, $product3->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);

        // do not keep categories (but do keep redirects)

        $config = new ImportConfig();
        $config->handleCategoryRewrites = ImportConfig::DELETE_CATEGORY_REWRITES;

        $importer = self::$factory->createImporter($config);
        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);
        $importer->flush();

        $expectedRewrites = [
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],

            ["product", "a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],

            ["product", "big-grass-green-box-product-import.html", "a-big-grass-green-box-product-import.html", "301", "1", "0", serialize([])],
        ];

        $expectedIndexes = [
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);

        // do not keep redirects

        $config = new ImportConfig();
        $config->handleRedirects = ImportConfig::DELETE_REDIRECTS;

        $product3->global()->setUrlKey("b-" . $product3->global()->getUrlKey());

        $importer = self::$factory->createImporter($config);
        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);
        $importer->flush();

        $expectedRewrites = [
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],

            ["product", "b-a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],

            ["product", "containers/b-a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$newCategoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$newCategoryId])],
        ];

        $expectedIndexes = [
            [(string)$newCategoryId, $product3->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);
    }

    /**
     * @throws Exception
     */
    public function testUrlRewritesWithJson()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->metadata = $objectManager->get(MetaData::class);

        $config = new ImportConfig();
        $this->metadata->valueSerializer = new JsonValueSerializer();

        $importer = self::$factory->createImporter($config);

        // create a product just for the category
        $productX = new SimpleProduct('cat-product-import');
        $productX->setAttributeSetByName("Default");
        $productX->addCategoriesByGlobalName(["Default Category/Boxes"]);
        $productX->global()->setName("Category dummy");
        $productX->global()->setPrice("0");
        $importer->importSimpleProduct($productX);
        $importer->flush();

        $categoryId = $productX->getCategoryIds()[0];

        // give this category a Dutch name
        $urlKeyAttributeId = $this->metadata->categoryAttributeMap['url_key'];

        self::$db->execute("
            INSERT IGNORE INTO `{$this->metadata->categoryEntityTable}_varchar`
            SET entity_id = ?, store_id = ?, attribute_id = ?, value = ?
        ", [
            $categoryId,
            1,
            $urlKeyAttributeId,
            'dozen'
        ]);

        // product
        $product1 = new SimpleProduct('3-product-import');
        $product1->setAttributeSetByName("Default");
        $product1->addCategoriesByGlobalName(["Default Category/Boxes"]);
        $product1->global()->setName("Big Red Box product-import");
        $product1->global()->setPrice("2.75");
        $product1->global()->setVisibility(ProductStoreView::VISIBILITY_IN_CATALOG);
        $product1->global()->generateUrlKey();

        $default = $product1->storeView('default');
        $default->setName("Grote Rode Doos product-import");
        $default->generateUrlKey();

        $importer->importSimpleProduct($product1);

        // another product
        $product3 = new SimpleProduct('4-product-import');
        $product3->setAttributeSetByName("Default");
        $product3->addCategoriesByGlobalName(["Default Category/Boxes"]);
        $product3->global()->setName("Big Grass Yellow Box product-import");
        $product3->global()->setPrice("2.65");
        $product3->global()->setVisibility(ProductStoreView::VISIBILITY_IN_CATALOG);
        $product3->global()->generateUrlKey();

        $importer->importSimpleProduct($product3);

        $importer->flush();

        // change url_key

        $product3->global()->setUrlKey("a-" . $product3->global()->getUrlKey());

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        // change categories

        $product3->addCategoriesByGlobalName(["Default Category/Containers"]);

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $newCategoryId = $product3->getCategoryIds()[0];

        $expectedRewrites = [
            ["product", "grote-rode-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "dozen/grote-rode-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                json_encode(['category_id' => (string)$categoryId])],

            ["product", "a-big-grass-yellow-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],

            ["product", "big-grass-yellow-box-product-import.html", "a-big-grass-yellow-box-product-import.html", "301", "1", "0", json_encode([])],

            ["product", "dozen/a-big-grass-yellow-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1",
                json_encode(['category_id' => (string)$categoryId])],

            ["product", "dozen/big-grass-yellow-box-product-import.html", "dozen/a-big-grass-yellow-box-product-import.html", "301", "1", "0",
                json_encode(['category_id' => (string)$categoryId])],

            ["product", "containers/a-big-grass-yellow-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$newCategoryId}", "0", "1", "1",
                json_encode(['category_id' => (string)$newCategoryId])],

        ];

        $expectedIndexes = [
            [(string)$categoryId, $product1->id],
            [(string)$categoryId, $product3->id],
            [(string)$newCategoryId, $product3->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);
    }

    private function doAsserts(array $expectedRewrites, array $expectedIndexes, Product $product1, Product $product3)
    {
        $productIds = "{$product1->id}, {$product3->id}";

        $actualErrors = [$product1->getErrors(), $product3->getErrors()];

        $this->assertEquals([[], []], $actualErrors);

        $actualRewrites = self::$db->fetchAllNonAssoc("
            SELECT `entity_type`, `request_path`, `target_path`, `redirect_type`, `store_id`, `is_autogenerated`, `metadata` FROM `" . $this->metadata->urlRewriteTable . "`
            WHERE `store_id` = 1 AND `entity_id` IN ({$productIds})
            ORDER BY `url_rewrite_id`
        ");

        $this->assertEquals($expectedRewrites, $actualRewrites);

        $actualIndexes = self::$db->fetchAllNonAssoc("
            SELECT `category_id`, `product_id` FROM `" . $this->metadata->urlRewriteProductCategoryTable . "` p
            INNER JOIN `" . $this->metadata->urlRewriteTable . "` uw ON uw.url_rewrite_id = p.url_rewrite_id AND uw.store_id = 1
            WHERE `product_id` IN ({$productIds})
            ORDER BY p.url_rewrite_id
        ");
        $this->assertEquals($expectedIndexes, $actualIndexes);

    }

    /**
     * @throws Exception
     */
    public function testSwitchUrlKeys()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->metadata = $objectManager->get(MetaData::class);

        $config = new ImportConfig();
        $this->metadata->valueSerializer = new SerializeValueSerializer();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ALLOW;

        $importer = self::$factory->createImporter($config);

        // create a product just for the category
        $productX = new SimpleProduct('cat-product-import');
        $productX->setAttributeSetByName("Default");
        $productX->addCategoriesByGlobalName(["Default Category/Names of Things"]);
        $productX->global()->setName("Category dummy");
        $productX->global()->setPrice("0");
        $importer->importSimpleProduct($productX);
        $importer->flush();

        $categoryId = $productX->getCategoryIds()[0];

        // product
        $product1 = new SimpleProduct('5-product-import');
        $product1->setAttributeSetByName("Default");
        $product1->addCategoriesByGlobalName(["Default Category/Names of Things"]);
        $product1->global()->setName("The First Name");
        $product1->global()->setPrice("2.75");
        $product1->global()->setVisibility(ProductStoreView::VISIBILITY_IN_CATALOG);
        $product1->global()->generateUrlKey();

        $importer->importSimpleProduct($product1);

        // another product
        $product2 = new SimpleProduct('6-product-import');
        $product2->setAttributeSetByName("Default");
        $product2->addCategoriesByGlobalName(["Default Category/Names of Things"]);
        $product2->global()->setName("The Second Name");
        $product2->global()->setPrice("2.65");
        $product2->global()->setVisibility(ProductStoreView::VISIBILITY_IN_CATALOG);
        $product2->global()->generateUrlKey();

        $importer->importSimpleProduct($product2);

        $importer->flush();

        // insert

        $expectedRewrites = [
            ["product", "the-first-name.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "names-of-things/the-first-name.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "the-second-name.html", "catalog/product/view/id/{$product2->id}", "0", "1", "1", null],
            ["product", "names-of-things/the-second-name.html", "catalog/product/view/id/{$product2->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
        ];

        $expectedIndexes = [
            [(string)$categoryId, $product1->id],
            [(string)$categoryId, $product2->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product2);

        // swap url_keys

        $product1->global()->setName('The Second Name');
        $product1->global()->generateUrlKey();
        $product2->global()->setName('The First Name');
        $product2->global()->generateUrlKey();

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $expectedRewrites = [

            ["product", "the-second-name.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "names-of-things/the-second-name.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "the-first-name.html", "catalog/product/view/id/{$product2->id}", "0", "1", "1", null],
            ["product", "names-of-things/the-first-name.html", "catalog/product/view/id/{$product2->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product2);
    }

    /**
     * @throws Exception
     */
    public function testReplaceUrlKey()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->metadata = $objectManager->get(MetaData::class);

        $keep = $this->metadata->saveRewritesHistory;
        $this->metadata->saveRewritesHistory = true;
        $this->metadata->valueSerializer = new SerializeValueSerializer();

        $config = new ImportConfig();

        $importer = self::$factory->createImporter($config);

        // create a product just for the category
        $productX = new SimpleProduct('cat-product-import');
        $productX->setAttributeSetByName("Default");
        $productX->addCategoriesByGlobalName(["Default Category/Names of Things"]);
        $productX->global()->setName("Category dummy");
        $productX->global()->setPrice("0");
        $importer->importSimpleProduct($productX);
        $importer->flush();

        $categoryId = $productX->getCategoryIds()[0];

        // product
        $product1 = new SimpleProduct('7-product-import');
        $product1->setAttributeSetByName("Default");
        $product1->addCategoriesByGlobalName(["Default Category/Names of Things"]);
        $product1->global()->setName("The Oldest Name");
        $product1->global()->setPrice("2.75");
        $product1->global()->setVisibility(ProductStoreView::VISIBILITY_IN_CATALOG);
        $product1->global()->generateUrlKey();

        $importer->importSimpleProduct($product1);
        $importer->flush();

        // create a rewrite

        $product1->global()->setName("The Old Name");
        $product1->global()->generateUrlKey();
        $importer->importSimpleProduct($product1);
        $importer->flush();

        $expectedRewrites = [
            ["product", "the-old-name.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "the-oldest-name.html", "the-old-name.html", "301", "1", "0", serialize([])],

            ["product", "names-of-things/the-old-name.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
            ["product", "names-of-things/the-oldest-name.html", "names-of-things/the-old-name.html", "301", "1", "0",
                serialize(['category_id' => (string)$categoryId])],

        ];

        $expectedIndexes = [
            [(string)$categoryId, $product1->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product1);

        // do not save rewrite history => earlier rewrites must still be updated

        $this->metadata->saveRewritesHistory = false;

        $product1->global()->setName('The New Name');
        $product1->global()->generateUrlKey();

        $importer->importSimpleProduct($product1);

        $importer->flush();

        $expectedRewrites = [

            ["product", "the-new-name.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "the-oldest-name.html", "the-new-name.html", "301", "1", "0", serialize([])],

            ["product", "names-of-things/the-new-name.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
            ["product", "names-of-things/the-oldest-name.html", "names-of-things/the-new-name.html", "301", "1", "0",
                serialize(['category_id' => (string)$categoryId])],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product1);

        $this->metadata->saveRewritesHistory = $keep;
    }
}
