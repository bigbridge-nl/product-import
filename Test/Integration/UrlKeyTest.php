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
        $product1->name = "Autumn Flowers";
        $product1->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct(1);
        $product1->sku = 'product-import-1#b';
        $product2->name = "Autumn Flowers";
        $product2->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(["Generated url key already exists: autumn-flowers"], $product2->errors);

        $product3 = $this->createProduct(1);
        $product3->sku = 'product-import-1#c';
        $product3->name = "Autumn Flowers";
        $product3->url_key = new GeneratedUrlKey();
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals(["Generated url key already exists: autumn-flowers"], $product3->errors);
    }

    public function testDuplicateUrlKeyOnAddSkuStrategy()
    {
        $config = new ImportConfig();
        $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU;

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = $this->createProduct(1);
        $product1->name = "Autumn Flowers";
        $product1->url_key = new GeneratedUrlKey();
        $product1->sku = 'product-import-2#a';
        $importer->importSimpleProduct($product1);

        $product2 = $this->createProduct(1);
        $product2->name = "Autumn Flowers";
        $product2->url_key = new GeneratedUrlKey();
        $product2->sku = 'product-import-2#b';
        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals([], $product2->errors);
        $this->assertEquals("autumn-flowers-product-import-2-b", $product2->url_key);

        $product3 = $this->createProduct(1);
        $product3->name = "Autumn Flowers";
        $product3->url_key = new GeneratedUrlKey();
        $product3->sku = 'product-import-2#c';
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->assertEquals([], $product3->errors);
        $this->assertEquals('autumn-flowers-product-import-2-c', $product3->url_key);
    }
}