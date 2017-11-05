<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\GeneratedUrlKey;
use BigBridge\ProductImport\Model\Reference;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;
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
class UrlKeyTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ImporterFactory */
    private static $factory;

    /** @var ProductRepositoryInterface $repository */
    private static $repository;

    /** @var  Magento2DbConnection */
    protected static $db;

    /** @var  Metadata */
    protected static $metadata;

    public static function setUpBeforeClass()
    {
        // include Magento
        require_once __DIR__ . '/../../../../../index.php';

        /** @var ImporterFactory $factory */
        self::$factory = ObjectManager::getInstance()->get(ImporterFactory::class);

        /** @var ProductRepositoryInterface $repository */
        self::$repository = ObjectManager::getInstance()->get(ProductRepositoryInterface::class);

        /** @var Magento2DbConnection $db */
        self::$db = ObjectManager::getInstance()->get(Magento2DbConnection::class);

        self::$metadata = ObjectManager::getInstance()->get(MetaData::class);

        $table = self::$metadata->productEntityTable;
        self::$db->execute("DELETE FROM `{$table}` WHERE sku LIKE 'product-import-%'");
    }

    public function createProduct($storeViewId)
    {
        $product1 = new SimpleProduct();
        $product1->name = "Big Turquoise Box";
        $product1->sku = uniqid('bb');
        $product1->price = "2.75";
        $product1->attribute_set_id = new Reference("Default");
        $product1->store_view_id = $storeViewId;

        return $product1;
    }

    public function testDuplicateUrlKeyOnDefaultStrategyCreateError()
    {
        $config = new ImportConfig();

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = $this->createProduct(1);
        $product1->sku = 'product-import-1#a';
        $product1->name = "Summer Flora";
        $product1->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct(1);
        $product2->sku = 'product-import-1#b';
        $product2->name = "Summer Flora";
        $product2->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(["Generated url key already exists: summer-flora"], $product2->errors);

        $product3 = $this->createProduct(1);
        $product3->sku = 'product-import-1#c';
        $product3->name = "Summer Flora";
        $product3->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals(["Generated url key already exists: summer-flora"], $product3->errors);
    }

    public function testDuplicateExplicitUrlKeyCreateError()
    {
        $config = new ImportConfig();

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = $this->createProduct(1);
        $product1->sku = 'product-import-6#a';
        $product1->name = "Flowers All Year";
        $product1->url_key = 'product-import-6';
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct(1);
        $product2->sku = 'product-import-6#b';
        $product2->name = "Flowers All Year";
        $product2->url_key = 'product-import-6';
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(["Url key already exists: product-import-6"], $product2->errors);

        $product3 = $this->createProduct(1);
        $product3->sku = 'product-import-6#c';
        $product3->name = "Flowers All Year";
        $product3->url_key = 'product-import-6';
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals(["Url key already exists: product-import-6"], $product3->errors);
    }

    public function testDuplicateUrlKeyOnAddSkuStrategy()
    {
        $config = new ImportConfig();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU;

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = $this->createProduct(1);
        $product1->name = "Winter Woozling";
        $product1->url_key = new GeneratedUrlKey();
        $product1->sku = 'product-import-2#a';
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct(1);
        $product2->name = "Winter Woozling";
        $product2->url_key = new GeneratedUrlKey();
        $product2->sku = 'product-import-2#b';
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->errors);
        $this->assertEquals("winter-woozling-product-import-2-b", $product2->url_key);

        $product3 = $this->createProduct(1);
        $product3->name = "Winter Woozling";
        $product3->url_key = new GeneratedUrlKey();
        $product3->sku = 'product-import-2#c';
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals([], $product3->errors);
        $this->assertEquals('winter-woozling-product-import-2-c', $product3->url_key);

        // resave product
        $product2->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->errors);
        $this->assertEquals('winter-woozling-product-import-2-b', $product2->url_key);
    }

    public function testDuplicateUrlKeyOnAddSerialStrategy()
    {
        $config = new ImportConfig();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL;

        list($importer,) = self::$factory->createImporter($config);

        // original
        $product1 = $this->createProduct(1);
        $product1->name = "Autumn Flowers";
        $product1->url_key = new GeneratedUrlKey();
        $product1->sku = 'product-import-3#a';
        $importer->importSimpleProduct($product1);

        // conflicting key
        $product2 = $this->createProduct(1);
        $product2->name = "Autumn Flowers";
        $product2->url_key = new GeneratedUrlKey();
        $product2->sku = 'product-import-3#b';
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->errors);
        $this->assertEquals("autumn-flowers-1", $product2->url_key);

        // conflicting key - different batch
        $product3 = $this->createProduct(1);
        $product3->name = "Autumn Flowers";
        $product3->url_key = new GeneratedUrlKey();
        $product3->sku = 'product-import-3#c';
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals([], $product3->errors);
        $this->assertEquals('autumn-flowers-2', $product3->url_key);
    }

    public function testSkuSchemeDuplicateUrlKeyOnAddSerialStrategy()
    {
        $config = new ImportConfig();
        $config->urlKeyScheme = ImportConfig::URL_KEY_SCHEME_FROM_SKU;
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL;

        list($importer,) = self::$factory->createImporter($config);

        // original
        $product1 = $this->createProduct(1);
        $product1->name = "Sunshine Every Day";
        $product1->url_key = new GeneratedUrlKey();
        $product1->sku = 'product-import-5#a';
        $importer->importSimpleProduct($product1);

        // conflicting key
        $product2 = $this->createProduct(1);
        $product2->name = "Moonlight Every Day";
        $product2->url_key = new GeneratedUrlKey();
        $product2->sku = 'product-import-5@a';
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->errors);
        $this->assertEquals("product-import-5-a-1", $product2->url_key);

        // conflicting key - different batch
        $product3 = $this->createProduct(1);
        $product3->name = "Starlight Every Day";
        $product3->url_key = new GeneratedUrlKey();
        $product3->sku = 'product-import-5#a';
        $importer->importSimpleProduct($product3);

        $product4 = $this->createProduct(1);
        $product4->name = "Planet Light Every Day";
        $product4->url_key = new GeneratedUrlKey();
        $product4->sku = 'product-import-5*a';
        $importer->importSimpleProduct($product4);

        $importer->flush();

        $this->assertEquals([], $product3->errors);
        $this->assertEquals([], $product4->errors);
        $this->assertEquals('product-import-5-a', $product3->url_key);
        $this->assertEquals('product-import-5-a-2', $product4->url_key);
    }

    public function testDifferentStoreViews()
    {
        $config = new ImportConfig();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL;

        list($importer, ) = self::$factory->createImporter($config);

        // original
        $product1 = $this->createProduct(0);
        $product1->name = "Spring Leaves";
        $product1->url_key = new GeneratedUrlKey();
        $product1->sku = 'product-import-4#a';
        $importer->importSimpleProduct($product1);

        // conflicting key
        $product2 = $this->createProduct(0);
        $product2->name = "Spring Leaves";
        $product2->url_key = new GeneratedUrlKey();
        $product2->sku = 'product-import-4#b';
        $importer->importSimpleProduct($product2);

        // same product, different store view
        $product1s1 = clone $product1;
        $product1s1->store_view_id = 1;
        $product1s1->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1s1);

        $product2s1 = clone $product2;
        $product2s1->store_view_id = 1;
        $product2s1->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2s1);

        $importer->flush();

        // resave products
        $product1c = clone $product1;
        $product1c->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1c);

        $product2c = clone $product2;
        $product2c->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2c);

        $product1s1c = clone $product1s1;
        $product1s1c->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1s1c);

        $product2s1c = clone $product2s1;
        $product2s1c->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2s1c);

        // same product same store view same batch

        $productJoker = clone $product2s1;
        $productJoker->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($productJoker);

        $importer->flush();

        $this->assertEquals([], $product1->errors);
        $this->assertEquals('spring-leaves', $product1->url_key);
        $this->assertEquals([], $product2->errors);
        $this->assertEquals('spring-leaves-1', $product2->url_key);
        $this->assertEquals([], $product1s1->errors);
        $this->assertEquals('spring-leaves', $product1s1->url_key);
        $this->assertEquals([], $product2s1->errors);
        $this->assertEquals('spring-leaves-1', $product2s1->url_key);
        $this->assertEquals([], $product1c->errors);
        $this->assertEquals('spring-leaves', $product1c->url_key);
        $this->assertEquals([], $product2c->errors);
        $this->assertEquals('spring-leaves-1', $product2c->url_key);
        $this->assertEquals([], $product1s1c->errors);
        $this->assertEquals('spring-leaves', $product1s1c->url_key);
        $this->assertEquals([], $product2s1c->errors);
        $this->assertEquals('spring-leaves-1', $product2s1c->url_key);
        $this->assertEquals([], $productJoker->errors);
        $this->assertEquals('spring-leaves-1', $productJoker->url_key);
    }
}