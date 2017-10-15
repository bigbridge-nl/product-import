<?php

namespace BigBridge\ProductImport\Test\Integration\Mass;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ObjectManager;

/**
 * This test only works on my laptop ;)
 *
 * Seriously, this test keeps track of the amount of time a large import takes.
 * If you are changing the code, do a pre-test with this class.
 * Then, when you're done, do a post test (or several) to check if the importer has not become intolerably slower.
 *
 * @author Patrick van Bergen
 */
class SpeedTest extends \PHPUnit_Framework_TestCase
{
    const PRODUCT_COUNT = 5000;

    /** @var  ImporterFactory */
    private static $factory;

    /** @var ProductRepositoryInterface $repository */
    private static $repository;

    public static function setUpBeforeClass()
    {
        // include Magento
        require_once __DIR__ . '/../../../../../index.php';

        /** @var ImporterFactory $factory */
        self::$factory = ObjectManager::getInstance()->get(ImporterFactory::class);

        /** @var ProductRepositoryInterface $repository */
        self::$repository = ObjectManager::getInstance()->get(ProductRepositoryInterface::class);
    }

    public function testInsertSpeed()
    {
        $success = true;

        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'status', 'price', 'visibility', 'special_from_date', 'tax_class_id'];
        $config->resultCallbacks[] = function (Product $product) use (&$success) {
            $success = $success && $product->ok;
        };

        $skus = [];
        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {
            $skus[$i] = uniqid("bb");
        }

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        list($importer, $error) = self::$factory->createImporter($config);

        $nameConverter = self::$factory->createNameConverter($config);

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Factory: " . $time . " seconds; " . $memory . " kB \n";

        $this->assertLessThan(0.1, $time);
        $this->assertLessThan(92, $memory); // cached metadata

        // ----------------------------------------------------

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {

            $product = new SimpleProduct();
            $product->name = uniqid("name");
            $product->sku = $skus[$i];
            $product->attribute_set_id = $nameConverter->convertNameToId('attribute_set_id', "Default");
            $product->status = Product::STATUS_ENABLED;
            $product->price = (string)rand(1, 100);
            $product->visibility = Product::VISIBILITY_BOTH;
            $product->special_from_date = "2017-10-14 01:22:03";
            $product->tax_class_id = 2;

            $importer->importSimpleProduct($product);
        }

        $importer->flush();

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Inserts: " . $time . " seconds; " . $memory . " kB \n";

        $this->assertTrue($success);
        $this->assertLessThan(3.3, $time);
        $this->assertLessThan(140, $memory); // the size of the last $product

        // ----------------------------------------------------

        $success = true;

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {

            $product = new SimpleProduct();
            $product->name = uniqid("name");
            $product->sku = $skus[$i];
            $product->attribute_set_id = $nameConverter->convertNameToId('attribute_set_id', "Default");
            $product->status = Product::STATUS_DISABLED;
            $product->price = (string)rand(1, 100);
            $product->visibility = Product::VISIBILITY_NOT_VISIBLE;
            $product->special_from_date = "2017-10-15 02:11:59";
            $product->tax_class_id = 3;

            $importer->importSimpleProduct($product);
        }

        $importer->flush();

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Updates: " . $time . " seconds; " . $memory . " Kb \n";

        $this->assertTrue($success);
        $this->assertLessThan(3.8, $time);
        $this->assertLessThan(1, $memory);
    }
}