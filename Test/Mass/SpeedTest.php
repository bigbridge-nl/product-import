<?php

namespace BigBridge\ProductImport\Test\Integration\Mass;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Data\ProductStoreView;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Importer;
use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;
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

        list($importer, $error) = self::$factory->createImporter($config);

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Factory: " . $time . " seconds; " . $memory . " kB \n";

        $this->assertLessThan(0.02, $time);
        $this->assertLessThan(400, $memory); // cached metadata

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
        $this->assertLessThan(4.6, $time);
        $this->assertLessThan(433, $memory); // the size of the last $product

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
        $this->assertLessThan(8.0, $time);
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
     */
    public function insertProducts($skus, $categories, Importer $importer)
    {
        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {

            $product = new SimpleProduct($skus[$i]);
            $product->attribute_set_id = new Reference("Default");
            $product->setCategoriesByGlobalName([$categories[0], $categories[1]]);

            $global = $product->global();
            $global->setName(uniqid("name"));
            $global->setDescription("A wunderful product that will enhance the quality of your live");
            $global->setShortDescription("A wunderful product");
            $global->setWeight("6");
            $global->setStatus(ProductStoreView::STATUS_ENABLED);
            $global->setPrice("1.39");
            $global->setSpecialPrice("1.25");
            $global->setSpecialFromDate("2017-10-22");
            $global->setSpecialToDate("2017-10-28");
            $global->setVisibility(ProductStoreView::VISIBILITY_BOTH);
            $global->setTaxClassName('Taxable Goods');
            $global->generateUrlKey();
            $global->website_ids = new References(['base']);

            $importer->importSimpleProduct($product);
        }

        $importer->flush();
    }

    /**
     * @param $skus
     * @param $categories
     * @param $importer
     */
    public function updateProducts($skus, $categories, Importer $importer)
    {
        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {

            $product = new SimpleProduct($skus[$i]);
            $product->attribute_set_id = new Reference("Default");
            $product->setCategoriesByGlobalName([$categories[1], $categories[2]]);

            $global = $product->global();
            $global->setName(uniqid("name"));
            $global->setDescription("A wonderful product that will enhance the quality of your life");
            $global->setShortDescription("A wonderful product");
            $global->setWeight("5.80");
            $global->setStatus(ProductStoreView::STATUS_DISABLED);
            $global->setPrice("1.39");
            $global->setSpecialPrice("1.15");
            $global->setSpecialFromDate("2017-12-10");
            $global->setSpecialToDate("2017-12-20");
            $global->setVisibility(ProductStoreView::VISIBILITY_NOT_VISIBLE);
            $global->setTaxClassName('Retail Customer');
            $global->website_ids = new References(['base']);
            $global->generateUrlKey();

            $importer->importSimpleProduct($product);
        }

        $importer->flush();
    }
}