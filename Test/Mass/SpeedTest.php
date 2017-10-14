<?php

namespace BigBridge\ProductImport\Test\Integration\Mass;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ObjectManager;

/**
 * @author Patrick van Bergen
 */
class SpeedTest extends \PHPUnit_Framework_TestCase
{
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
        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'status', 'price', 'special_from_date'];

        $skus = [];
        for ($i = 0; $i < 5000; $i++) {
            $skus[$i] = uniqid("bb");
        }

        $before = microtime(true);

        list($importer, $error) = self::$factory->create($config);

        $success = true;

        for ($i = 0; $i < 5000; $i++) {

            $product = new SimpleProduct();
            $product->name = uniqid("name");
            $product->sku = $skus[$i];
            $product->attribute_set_name = "Default";
            $product->status = Product::STATUS_ENABLED;
            $product->price = (string)rand(1, 100);
            $product->special_from_date = "2017-10-14 01:22:03";

            list($ok, $error) = $importer->insert($product);
            $success = $success && $ok;

            $results[] = [$ok, $error];
        }

        $importer->flush();

        $after = microtime(true);
        $time = $after - $before;

        echo "Inserts: " . $time . " seconds\n";

        $this->assertTrue($success);
        $this->assertLessThan(2.5, $time);

        // --------------------------

        $before = microtime(true);

        $success = true;

        for ($i = 0; $i < 5000; $i++) {

            $product = new SimpleProduct();
            $product->name = uniqid("name");
            $product->sku = $skus[$i];
            $product->attribute_set_name = "Default";
            $product->status = Product::STATUS_DISABLED;
            $product->price = (string)rand(1, 100);
            $product->special_from_date = "2017-10-15 02:11:59";

            list($ok, $error) = $importer->insert($product);
            $success = $success && $ok;

            $results[] = [$ok, $error];
        }

        $importer->flush();

        $after = microtime(true);
        $time = $after - $before;

        echo "Updates: " . $time . " seconds\n";

        $this->assertTrue($success);
        $this->assertLessThan(3.0, $time);
    }
}