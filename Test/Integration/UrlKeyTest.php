<?php

namespace BigBridge\ProductImport\Test\Integration;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * Tests in this class take the form of
 *
 * - insert product a
 * - insert product b with conflicting url_key - in the same batch
 * - insert product b with conflicting url_key - in a different batch
 * - resave product b with conflicting url_key
 *
 * @author Patrick van Bergen
 */
class UrlKeyTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /** @var  ImporterFactory */
    private static $factory;

    /** @var ProductRepositoryInterface $repository */
    private static $repository;

    /** @var  Magento2DbConnection */
    protected static $db;

    /** @var  Metadata */
    protected static $metadata;

    public static function setUpBeforeClass(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);

        /** @var ProductRepositoryInterface $repository */
        self::$repository = $objectManager->get(ProductRepositoryInterface::class);

        /** @var Magento2DbConnection $db */
        self::$db = $objectManager->get(Magento2DbConnection::class);

        self::$metadata = $objectManager->get(MetaData::class);

        $table = self::$metadata->productEntityTable;
        self::$db->execute("DELETE FROM `{$table}` WHERE sku LIKE 'product-import-%'");
    }

    /**
     * @param $sku
     * @return SimpleProduct
     */
    public function createProduct($sku)
    {
        $product1 = new SimpleProduct($sku);
        $product1->setAttributeSetByName("Default");

        $product1->global()->setName("Big Turquoise Box");
        $product1->global()->setPrice("2.75");

        return $product1;
    }

    /**
     * @throws Exception
     */
    public function testDuplicateUrlKeyOnDefaultStrategyCreateError()
    {
        $config = new ImportConfig();

        $importer = self::$factory->createImporter($config);

        $product1 = $this->createProduct('product-import-1#a');
        $product1->storeView('default')->setName("Summer Flora");
        $product1->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct('product-import-1#b');
        $product2->storeView('default')->setName("Summer Flora");
        $product2->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(["generated url key already exists: summer-flora"], $product2->getErrors());

        $product3 = $this->createProduct('product-import-1#c');
        $product3->storeView('default')->setName("Summer Flora");
        $product3->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals(["generated url key already exists: summer-flora"], $product3->getErrors());
    }

    /**
     * @throws Exception
     */
    public function testDuplicateExplicitUrlKeyCreateError()
    {
        $config = new ImportConfig();

        $importer = self::$factory->createImporter($config);

        $product1 = $this->createProduct('product-import-6#a');
        $product1->storeView('default')->setName("Flowers All Year");
        $product1->storeView('default')->setUrlKey('product-import-6');
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct('product-import-6#b');
        $product2->storeView('default')->setName("Flowers All Year");
        $product2->storeView('default')->setUrlKey('product-import-6');
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(["url key already exists: product-import-6 in store view default"], $product2->getErrors());

        $product3 = $this->createProduct('product-import-6#c');
        $product3->storeView('default')->setName("Flowers All Year");
        $product3->storeView('default')->setUrlKey('product-import-6');
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals(["url key already exists: product-import-6 in store view default"], $product3->getErrors());
    }

    /**
     * @throws Exception
     */
    public function testDuplicateUrlKeyOnAddSkuStrategy()
    {
        $config = new ImportConfig();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU;

        $importer = self::$factory->createImporter($config);

        $product1 = $this->createProduct('product-import-2#a');
        $product1->storeView('default')->setName("Winter Woozling");
        $product1->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct('product-import-2#b');
        $product2->storeView('default')->setName("Winter Woozling");
        $product2->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product2);

        $product3 = $this->createProduct('product-import-2#c');
        $product3->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals([], $product2->getErrors());
        $this->assertEquals(['url key is based on name and product has no name in store view'], $product3->getErrors());
        $this->assertEquals("winter-woozling-product-import-2-b", $product2->storeView('default')->getUrlKey());

        $product3 = $this->createProduct('product-import-2#c');
        $product3->storeView('default')->setName("Winter Woozling");
        $product3->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals([], $product3->getErrors());
        $this->assertEquals('winter-woozling-product-import-2-c', $product3->storeView('default')->getUrlKey());

        // resave product
//        $product2->global()->generateUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->getErrors());
        $this->assertEquals('winter-woozling-product-import-2-b', $product2->storeView('default')->getUrlKey());
    }

    /**
     * @throws Exception
     */
    public function testDuplicateUrlKeyOnAddSerialStrategy()
    {
        $config = new ImportConfig();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL;

        $importer = self::$factory->createImporter($config);

        // original
        $product1 = $this->createProduct('product-import-3#a');
        $product1->storeView('default')->setName("Autumn Flowers");
        $product1->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product1);

        // conflicting key
        $product2 = $this->createProduct('product-import-3#b');
        $product2->storeView('default')->setName("Autumn Flowers");
        $product2->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product2);

        // conflicting key, same batch
        $product3 = $this->createProduct('product-import-3#c');
        $product3->storeView('default')->setName("Autumn Flowers");
        $product3->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals([], $product2->getErrors());
        $this->assertEquals([], $product3->getErrors());
        $this->assertEquals("autumn-flowers-1", $product2->storeView('default')->getUrlKey());
        $this->assertEquals("autumn-flowers-2", $product3->storeView('default')->getUrlKey());

        // conflicting key - different batch
        $product3 = $this->createProduct('product-import-3#c');
        $product3->storeView('default')->setName("Autumn Flowers");
        $product3->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals([], $product3->getErrors());
        $this->assertEquals('autumn-flowers-2', $product3->storeView('default')->getUrlKey());
    }

    /**
     * @throws Exception
     */
    public function testSkuSchemeDuplicateUrlKeyOnAddSerialStrategy()
    {
        $config = new ImportConfig();
        $config->urlKeyScheme = ImportConfig::URL_KEY_SCHEME_FROM_SKU;
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL;

        $importer = self::$factory->createImporter($config);

        // original
        $product1 = $this->createProduct('product-import-5#a');
        $product1->storeView('default')->setName("Sunshine Every Day");
        $product1->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product1);

        // conflicting key
        $product2 = $this->createProduct('product-import-5@a');
        $product2->storeView('default')->setName("Moonlight Every Day");
        $product2->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product2);

        // conflicting key - same batch
        $product3 = $this->createProduct('product-import-5!a');
        $product3->storeView('default')->setName("Moonlight Every Day");
        $product3->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals([], $product2->getErrors());
        $this->assertEquals([], $product3->getErrors());
        $this->assertEquals("product-import-5-a-1", $product2->storeView('default')->getUrlKey());
        $this->assertEquals("product-import-5-a-2", $product3->storeView('default')->getUrlKey());

        // conflicting key - different batch
        $product3 = $this->createProduct('product-import-5#a');
        $product3->storeView('default')->setName("Starlight Every Day");
        $product3->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product3);

        $product4 = $this->createProduct('product-import-5*a');
        $product4->storeView('default')->setName("Planet Light Every Day");
        $product4->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($product4);

        $importer->flush();

        $this->assertEquals([], $product3->getErrors());
        $this->assertEquals([], $product4->getErrors());
        $this->assertEquals('product-import-5-a', $product3->storeView('default')->getUrlKey());
        $this->assertEquals('product-import-5-a-3', $product4->storeView('default')->getUrlKey());
    }

    /**
     * @throws Exception
     */
    public function testDifferentStoreViews()
    {
        $config = new ImportConfig();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL;

        $importer = self::$factory->createImporter($config);

        // original
        $product1 = $this->createProduct('product-import-4#a');
        $product1->global()->setName("Spring Leaves");
        $product1->global()->generateUrlKey();

        // conflicting key
        $product2 = $this->createProduct('product-import-4#b');
        $product2->global()->setName("Spring Leaves");
        $product2->global()->generateUrlKey();

        $product1s1 = $product1->storeView('default');
        $product1s1->setName("Spring Leaves");
        $product1s1->generateUrlKey();

        $product2s1 = $product2->storeView('default');
        $product2s1->setName("Spring Leaves");
        $product2s1->generateUrlKey();

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product2);

        $importer->flush();

        // resave products
        $product1c = clone $product1;
        $product1c->global()->generateUrlKey();

        $product2c = clone $product2;
        $product2c->global()->generateUrlKey();

        $product1s1c = $product1c->storeView('default');
        $product1s1c->setName("Spring Leaves");
        $product1s1c->generateUrlKey();

        $product2s1c = $product2c->storeView('default');
        $product2s1c->setName("Spring Leaves");
        $product2s1c->generateUrlKey();

        $importer->importSimpleProduct($product1c);
        $importer->importSimpleProduct($product2c);

        // same product same store view same batch

        $productJoker = clone $product2;
        $productJoker->storeView('default')->setName("Spring Leaves");
        $productJoker->storeView('default')->generateUrlKey();
        $importer->importSimpleProduct($productJoker);

        $importer->flush();

        $this->assertEquals([], $product1->getErrors());
        $this->assertEquals('spring-leaves', $product1->global()->getUrlKey());
        $this->assertEquals([], $product2->getErrors());
        $this->assertEquals('spring-leaves-1', $product2->global()->getUrlKey());
        $this->assertEquals('spring-leaves', $product1->storeView('default')->getUrlKey());
        $this->assertEquals('spring-leaves-1', $product2->storeView('default')->getUrlKey());
        $this->assertEquals([], $product1c->getErrors());
        $this->assertEquals('spring-leaves', $product1c->global()->getUrlKey());
        $this->assertEquals([], $product2c->getErrors());
        $this->assertEquals('spring-leaves-1', $product2c->global()->getUrlKey());
        $this->assertEquals('spring-leaves', $product1c->storeView('default')->getUrlKey());
        $this->assertEquals('spring-leaves-1', $product2c->storeView('default')->getUrlKey());
        $this->assertEquals([], $productJoker->getErrors());
        $this->assertEquals('spring-leaves-1', $productJoker->storeView('default')->getUrlKey());
    }
}
