<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Importer;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;

/**
 * This test keeps track of the amount of time a large import takes.
 * If you are changing the code, do a pre-test with this class.
 * Then, when you're done, do a post test (or several) to check if the importer has not become intolerably slower.
 *
 * @author Patrick van Bergen
 */
class MemoryTest extends \Magento\TestFramework\TestCase\AbstractController
{
    const PRODUCT_COUNT = 2500;

    /** @var  ImporterFactory */
    private static $factory;

    public static function setUpBeforeClass(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);
    }

    /**
     * @throws \Exception
     */
    public function testImport()
    {
        $lastErrors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$success, &$lastErrors) {
            if ($product->getErrors()) {
                $lastErrors = $product->getErrors();
            }
        };

        $skus = [];
        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {
            $skus[$i] = uniqid("bb");
        }

        $categories = ['Default Category/Test category 1', 'Default Category/Test category 2', 'Default Category/Test category 3'];

        $beforePeakMemory = memory_get_peak_usage();

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        $importer = self::$factory->createImporter($config);

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Factory: " . sprintf('%.3f', $time) . " seconds; " . $memory . " kB \n";

        $this->assertLessThan(900, $memory); // cached metadata

        // ----------------------------------------------------

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        $this->insertProducts($skus, $categories, $importer);

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Inserts: " . sprintf('%.1f', $time) . " seconds; " . $memory . " kB \n";

        $this->assertSame([], $lastErrors);
        $this->assertLessThan(680, $memory); // the size of the last $product

        // ----------------------------------------------------

        $beforeMemory = memory_get_usage();
        $beforeTime = microtime(true);

        $this->updateProducts($skus, $categories, $importer);

        $afterTime = microtime(true);
        $afterMemory = memory_get_usage();
        $time = $afterTime - $beforeTime;
        $memory = (int)(($afterMemory - $beforeMemory) / 1000);

        echo "Updates: " . sprintf('%.1f', $time) . " seconds; " . $memory . " kB \n";

        $this->assertSame([], $lastErrors);
        $this->assertLessThan(67, $memory);

        $afterPeakMemory = memory_get_peak_usage();

        // this not a good tool to measure actual memory use, but it does say something about the amount of memory the import takes
        $peakMemory = (int)(($afterPeakMemory - $beforePeakMemory) / 1000000);
        $this->assertLessThan(40, $peakMemory);

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
            $product->addCategoriesByGlobalName([$categories[0], $categories[1]]);
            $product->setWebsitesByCode(['base']);

            $global = $product->global();
            $global->setName(uniqid("name"));
            $global->setDescription("A wunderful product that will enhance the quality of your live");
            $global->setShortDescription("A wunderful product");
            $global->setMetaTitle("Wonderful product");
            $global->setMetaDescription("Wonderful product, life changer");
            $global->setMetaKeywords("wonderful, life changer");
            $global->setWeight("6");
            $global->setStatus(ProductStoreView::STATUS_DISABLED);
            $global->setPrice("1.39");
            $global->setSpecialPrice("1.25");
            $global->setSpecialFromDate("2017-10-22");
            $global->setSpecialToDate("2017-10-28");
            $global->setNewsFromDate("2017-12-10");
            $global->setNewsToDate("2017-12-20");
            $global->setCost("0.1");
            $global->setVisibility(ProductStoreView::VISIBILITY_IN_CATALOG);
            $global->setTaxClassName('Taxable Goods');
            $global->generateUrlKey();
            $global->setCountryOfManufacture('NL');
            $global->setMsrp('8.95');
            $global->setMsrpDisplayActualPriceType(ProductStoreView::MSRP_USE_CONFIG);

            $stock = $product->defaultStockItem();
            $stock->setQty(100);
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
            $product->addCategoriesByGlobalName([$categories[1], $categories[2]]);
            $product->setWebsitesByCode(['base']);

            $global = $product->global();
            $global->setName(uniqid("name"));
            $global->setDescription("A wonderful product that will enhance the quality of your life");
            $global->setShortDescription("A wonderful product");
            $global->setMetaTitle("Wonderful product");
            $global->setMetaDescription("Wonderful product, lifechanger");
            $global->setMetaKeywords("wonderful, lifechanger");
            $global->setWeight("5.80");
            $global->setStatus(ProductStoreView::STATUS_ENABLED);
            $global->setPrice("1.39");
            $global->setSpecialPrice("1.15");
            $global->setSpecialFromDate("2017-12-10");
            $global->setSpecialToDate("2017-12-20");
            $global->setNewsFromDate("2017-12-10");
            $global->setNewsToDate("2017-12-20");
            $global->setCost("0.2");
            $global->setVisibility(ProductStoreView::VISIBILITY_BOTH);
            $global->setTaxClassName('Retail Customer');
            $global->generateUrlKey();

            $stock = $product->defaultStockItem();
            $stock->setQty(98);
            $stock->setIsInStock(true);

            $importer->importSimpleProduct($product);
        }

        $importer->flush();
    }
}
