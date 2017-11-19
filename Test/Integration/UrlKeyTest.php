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

    public function createProduct($sku)
    {
        $product1 = new SimpleProduct($sku);
        $product1->attribute_set_id = new Reference("Default");

        $product1->global()->name = "Big Turquoise Box";
        $product1->global()->price = "2.75";

        return $product1;
    }

    public function testDuplicateUrlKeyOnDefaultStrategyCreateError()
    {
        $config = new ImportConfig();

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = $this->createProduct('product-import-1#a');
        $product1->storeView('default')->name = "Summer Flora";
        $product1->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct('product-import-1#b');
        $product2->storeView('default')->name = "Summer Flora";
        $product2->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(["Generated url key already exists: summer-flora"], $product2->errors);

        $product3 = $this->createProduct('product-import-1#c');
        $product3->storeView('default')->name = "Summer Flora";
        $product3->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals(["Generated url key already exists: summer-flora"], $product3->errors);
    }

    public function testDuplicateExplicitUrlKeyCreateError()
    {
        $config = new ImportConfig();

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = $this->createProduct('product-import-6#a');
        $product1->storeView('default')->name = "Flowers All Year";
        $product1->storeView('default')->url_key = 'product-import-6';
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct('product-import-6#b');
        $product2->storeView('default')->name = "Flowers All Year";
        $product2->storeView('default')->url_key = 'product-import-6';
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(["Url key already exists: product-import-6"], $product2->errors);

        $product3 = $this->createProduct('product-import-6#c');
        $product3->storeView('default')->name = "Flowers All Year";
        $product3->storeView('default')->url_key = 'product-import-6';
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals(["Url key already exists: product-import-6"], $product3->errors);
    }

    public function testDuplicateUrlKeyOnAddSkuStrategy()
    {
        $config = new ImportConfig();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU;

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = $this->createProduct('product-import-2#a');
        $product1->storeView('default')->name = "Winter Woozling";
        $product1->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct('product-import-2#b');
        $product2->storeView('default')->name = "Winter Woozling";
        $product2->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->errors);
        $this->assertEquals("winter-woozling-product-import-2-b", $product2->url_key);

        $product3 = $this->createProduct('product-import-2#c');
        $product3->storeView('default')->name = "Winter Woozling";
        $product3->storeView('default')->url_key = new GeneratedUrlKey();
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
        $product1 = $this->createProduct('product-import-3#a');
        $product1->storeView('default')->name = "Autumn Flowers";
        $product1->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1);

        // conflicting key
        $product2 = $this->createProduct('product-import-3#b');
        $product2->storeView('default')->name = "Autumn Flowers";
        $product2->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->errors);
        $this->assertEquals("autumn-flowers-1", $product2->url_key);

        // conflicting key - different batch
        $product3 = $this->createProduct('product-import-3#c');
        $product3->storeView('default')->name = "Autumn Flowers";
        $product3->storeView('default')->url_key = new GeneratedUrlKey();
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
        $product1 = $this->createProduct('product-import-5#a');
        $product1->storeView('default')->name = "Sunshine Every Day";
        $product1->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1);

        // conflicting key
        $product2 = $this->createProduct('product-import-5@a');
        $product2->storeView('default')->name = "Moonlight Every Day";
        $product2->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->errors);
        $this->assertEquals("product-import-5-a-1", $product2->url_key);

        // conflicting key - different batch
        $product3 = $this->createProduct('product-import-5#a');
        $product3->storeView('default')->name = "Starlight Every Day";
        $product3->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product3);

        $product4 = $this->createProduct('product-import-5*a');
        $product4->storeView('default')->name = "Planet Light Every Day";
        $product4->storeView('default')->url_key = new GeneratedUrlKey();
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
        $product1 = $this->createProduct('product-import-4#a');
        $product1->global()->name = "Spring Leaves";
        $product1->global()->url_key = new GeneratedUrlKey();

        // conflicting key
        $product2 = $this->createProduct('product-import-4#b');
        $product2->global()->name = "Spring Leaves";
        $product2->global()->url_key = new GeneratedUrlKey();

        $product1s1 = $product1->storeView('default');
        $product1s1->name = "Spring Leaves";
        $product1s1->url_key = new GeneratedUrlKey();

        $product2s1 = $product2->storeView('default');
        $product2s1->name = "Spring Leaves";
        $product2s1->url_key = new GeneratedUrlKey();

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product2);

        $importer->flush();

        // resave products
        $product1c = clone $product1;
        $product1c->url_key = new GeneratedUrlKey();

        $product2c = clone $product2;
        $product2c->url_key = new GeneratedUrlKey();

        $product1s1c = $product1c->storeView('default');
        $product1s1c->name = "Spring Leaves";
        $product1s1c->url_key = new GeneratedUrlKey();

        $product2s1c = $product2c->storeView('default');
        $product2s1c->name = "Spring Leaves";
        $product2s1c->url_key = new GeneratedUrlKey();

        $importer->importSimpleProduct($product1c);
        $importer->importSimpleProduct($product2c);

        // same product same store view same batch

        $productJoker = clone $product2;
        $productJoker->storeView('default')->name = "Spring Leaves";
        $productJoker->storeView('default')->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($productJoker);

        $importer->flush();

        $this->assertEquals([], $product1->errors);
        $this->assertEquals('spring-leaves', $product1->global()->url_key);
        $this->assertEquals([], $product2->errors);
        $this->assertEquals('spring-leaves-1', $product2->global()->url_key);
        $this->assertEquals('spring-leaves', $product1->storeView('default')->url_key);
        $this->assertEquals('spring-leaves-1', $product2->storeView('default')->url_key);
        $this->assertEquals([], $product1c->errors);
        $this->assertEquals('spring-leaves', $product1c->global()->url_key);
        $this->assertEquals([], $product2c->errors);
        $this->assertEquals('spring-leaves-1', $product2c->global()->url_key);
        $this->assertEquals('spring-leaves', $product1c->storeView('default')->url_key);
        $this->assertEquals('spring-leaves-1', $product2c->storeView('default')->url_key);
        $this->assertEquals([], $productJoker->errors);
        $this->assertEquals('spring-leaves-1', $productJoker->storeView('default')->url_key);
    }
}