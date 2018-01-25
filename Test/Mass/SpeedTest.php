<?php

namespace BigBridge\ProductImport\Test\Integration\Mass;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ObjectManager;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Importer;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;

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
    const PRODUCT_COUNT = 2500;

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

    /**
     * @throws \Exception
     */
    public function testImportSpeed()
    {
        $success = true;
        $lastErrors = [];

        $config = new ImportConfig();
        $config->resultCallbacks[] = function (Product $product) use (&$success, &$lastErrors) {
            $success = $success && $product->isOk();
            if ($product->getErrors()) {
                $lastErrors = $product->getErrors();
            }
        };

        $skus = [];
        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {
            $skus[$i] = uniqid("bb");
        }

        $categories = ['Test category 1', 'Test category 2', 'Test category 3'];

        $beforePeakMemory = memory_get_peak_usage();

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        $importer = self::$factory->createImporter($config);

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Factory: " . $time . " seconds; " . $memory . " kB \n";

        $this->assertLessThan(0.031, $time);
        $this->assertLessThan(800, $memory); // cached metadata

        // ----------------------------------------------------

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        $this->insertProducts($skus, $categories, $importer);

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Inserts: " . $time . " seconds; " . $memory . " kB \n";

        $this->assertSame([], $lastErrors);
        $this->assertTrue($success);
        $this->assertLessThan(7.7, $time);
        $this->assertLessThan(562, $memory); // the size of the last $product

        // ----------------------------------------------------

        $success = true;

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        $this->updateProducts($skus, $categories, $importer);

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Updates: " . $time . " seconds; " . $memory . " kB \n";

        $this->assertSame([], $lastErrors);
        $this->assertTrue($success);
        $this->assertLessThan(11.1, $time);
        // 65K is not leaked but "held" by PHP for the large array $updatedRewrites in UrlRewriteStorage::rewriteExistingRewrites
        // try running updateProducts twice, the memory consumed does not accumulate
        $this->assertLessThan(66, $memory);

        $afterPeakMemory = memory_get_peak_usage();

        // this not a good tool to measure actual memory use, but it does say something about the amount of memory the import takes
        $peakMemory = (int)(($afterPeakMemory - $beforePeakMemory) / 1000000);
        $this->assertLessThan(16, $peakMemory);

        echo "Peak mem: " . $peakMemory . " MB \n";
    }

    /**
     * @param $skus
     * @param $categories
     * @param $importer
     * @throws \Exception
     */
    public function insertProducts($skus, $categories, Importer $importer)
    {
        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {

            $product = new SimpleProduct($skus[$i]);
            $product->setAttributeSetByName("Default");
            $product->setCategoriesByGlobalName([$categories[0], $categories[1]]);
            $product->setWebsitesByCode(['base']);

            $global = $product->global();
            $global->setName(uniqid("name"));
            $global->setDescription("A wunderful product that will enhance the quality of your live");
            $global->setShortDescription("A wunderful product");
            $global->setMetaTitle("Wonderful product");
            $global->setMetaDescription("Wonderful product, life changer");
            $global->setMetaKeywords("wonderful, life changer");
            $global->setWeight("6");
            $global->setStatus(ProductStoreView::STATUS_ENABLED);
            $global->setPrice("1.39");
            $global->setSpecialPrice("1.25");
            $global->setSpecialFromDate("2017-10-22");
            $global->setSpecialToDate("2017-10-28");
            $global->setNewsFromDate("2017-12-10");
            $global->setNewsToDate("2017-12-20");
            $global->setCost("0.1");
            $global->setVisibility(ProductStoreView::VISIBILITY_BOTH);
            $global->setTaxClassName('Taxable Goods');
            $global->generateUrlKey();
            $global->setCountryOfManufacture('NL');

            $stock = $product->defaultStockItem();
            $stock->setQuantity(100);
            $stock->setIsInStock(true);

            $importer->importSimpleProduct($product);
        }

        $importer->flush();
    }

    /**
     * @param $skus
     * @param $categories
     * @param $importer
     * @throws \Exception
     */
    public function updateProducts($skus, $categories, Importer $importer)
    {
        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {

            $product = new SimpleProduct($skus[$i]);
            $product->setAttributeSetByName("Default");
            $product->setCategoriesByGlobalName([$categories[1], $categories[2]]);
            $product->setWebsitesByCode(['base']);

            $global = $product->global();
            $global->setName(uniqid("name"));
            $global->setDescription("A wonderful product that will enhance the quality of your life");
            $global->setShortDescription("A wonderful product");
            $global->setMetaTitle("Wonderful product");
            $global->setMetaDescription("Wonderful product, lifechanger");
            $global->setMetaKeywords("wonderful, lifechanger");
            $global->setWeight("5.80");
            $global->setStatus(ProductStoreView::STATUS_DISABLED);
            $global->setPrice("1.39");
            $global->setSpecialPrice("1.15");
            $global->setSpecialFromDate("2017-12-10");
            $global->setSpecialToDate("2017-12-20");
            $global->setNewsFromDate("2017-12-10");
            $global->setNewsToDate("2017-12-20");
            $global->setCost("0.2");
            $global->setVisibility(ProductStoreView::VISIBILITY_NOT_VISIBLE);
            $global->setTaxClassName('Retail Customer');
            $global->generateUrlKey();

            $stock = $product->defaultStockItem();
            $stock->setQuantity(98);
            $stock->setIsInStock(true);

            $importer->importSimpleProduct($product);
        }

        $importer->flush();
    }
}