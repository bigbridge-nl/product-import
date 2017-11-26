<?php

namespace BigBridge\ProductImport\Test\Integration;

use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Api\SimpleProduct;
use BigBridge\ProductImport\Api\ImporterFactory;

/**
 * @author Patrick van Bergen
 */
class UrlRewriteTest extends \PHPUnit_Framework_TestCase
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
        self::$db->execute("DELETE FROM `{$table}` WHERE sku LIKE '%-product-import'");
        $table = self::$metadata->urlRewriteTable;
        self::$db->execute("DELETE FROM `{$table}` WHERE request_path LIKE '%product-import.html'");
    }

    public function testUrlRewritesGeneration()
    {
        $config = new ImportConfig();
        $config->magentoVersion = "2.1.8";

        list($importer, ) = self::$factory->createImporter($config);

        // product
        $product1 = new SimpleProduct('1-product-import');
        $product1->setAttributeSetByName("Default");
        $product1->setCategoriesByGlobalName(["Boxes"]);
        $product1->global()->setName("Big Turquoise Box product-import");
        $product1->global()->setPrice("2.75");
        $product1->global()->generateUrlKey();

        // same sku, different store view
        $default = $product1->storeView('default');
        $default->setName("Grote Turquoise Doos product-import");
        $default->generateUrlKey();

        $importer->importSimpleProduct($product1);

        // another product
        $product3 = new SimpleProduct('2-product-import');
        $product3->setAttributeSetByName("Default");
        $product3->setCategoriesByGlobalName(["Boxes"]);
        $product3->global()->setName("Big Grass Green Box product-import");
        $product3->global()->setPrice("2.65");
        $product3->global()->generateUrlKey();

        $importer->importSimpleProduct($product3);

        $importer->flush();

        $categoryId = $product1->getCategoryIds()[0];

        // insert

        $expectedRewrites = [
#todo "dozen"
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "boxes/grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],
            ["product", "boxes/big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
        ];

        $expectedIndexes = [
            [$categoryId, $product1->id],
            [$categoryId, $product3->id],
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
#todo "dozen"
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "boxes/grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],
            ["product", "boxes/a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "big-grass-green-box-product-import.html", "a-big-grass-green-box-product-import.html", "301", "1", "0", serialize([])],
            ["product", "boxes/big-grass-green-box-product-import.html", "boxes/a-big-grass-green-box-product-import.html", "301", "1", "0",
                serialize(['category_id' => (string)$categoryId])],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);

        // change categories

        $product3->setCategoriesByGlobalName(["Containers"]);

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $newCategoryId = $product3->getCategoryIds()[0];

        $expectedRewrites = [
#todo "dozen"
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "boxes/grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],

            ["product", "a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],
            ["product", "boxes/a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$categoryId])],
            ["product", "containers/a-big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$newCategoryId}", "0", "1", "1",
                serialize(['category_id' => (string)$newCategoryId])],

            ["product", "big-grass-green-box-product-import.html", "a-big-grass-green-box-product-import.html", "301", "1", "0", serialize([])],
            ["product", "boxes/big-grass-green-box-product-import.html", "boxes/a-big-grass-green-box-product-import.html", "301", "1", "0",
                serialize(['category_id' => (string)$categoryId])],
        ];

        $expectedIndexes = [
            [$categoryId, $product1->id],
            [$categoryId, $product3->id],
            [$newCategoryId, $product3->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);
    }

    public function testUrlRewritesWithJson()
    {
        $config = new ImportConfig();
        $config->magentoVersion = "2.2.1";

        list($importer, ) = self::$factory->createImporter($config);

        // product
        $product1 = new SimpleProduct('3-product-import');
        $product1->setAttributeSetByName("Default");
        $product1->setCategoriesByGlobalName(["Boxes"]);
        $product1->global()->setName("Big Red Box product-import");
        $product1->global()->setPrice("2.75");
        $product1->global()->generateUrlKey();

        $default = $product1->storeView('default');
        $default->setName("Grote Rode Doos product-import");
        $default->generateUrlKey();

        $importer->importSimpleProduct($product1);

        // another product
        $product3 = new SimpleProduct('4-product-import');
        $product3->setAttributeSetByName("Default");
        $product3->setCategoriesByGlobalName(["Boxes"]);
        $product3->global()->setName("Big Grass Yellow Box product-import");
        $product3->global()->setPrice("2.65");
        $product3->global()->generateUrlKey();

        $importer->importSimpleProduct($product3);

        $importer->flush();

        $categoryId = $product1->getCategoryIds()[0];

        // change url_key

        $product3->global()->setUrlKey("a-" . $product3->global()->getUrlKey());

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        // change categories

        $product3->setCategoriesByGlobalName(["Containers"]);

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $newCategoryId = $product3->getCategoryIds()[0];

        $expectedRewrites = [
#todo "dozen"
            ["product", "grote-rode-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "boxes/grote-rode-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1",
                json_encode(['category_id' => (string)$categoryId])],

            ["product", "a-big-grass-yellow-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],
            ["product", "boxes/a-big-grass-yellow-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1",
                json_encode(['category_id' => (string)$categoryId])],
            ["product", "containers/a-big-grass-yellow-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$newCategoryId}", "0", "1", "1",
                json_encode(['category_id' => (string)$newCategoryId])],

            ["product", "big-grass-yellow-box-product-import.html", "a-big-grass-yellow-box-product-import.html", "301", "1", "0", json_encode([])],
            ["product", "boxes/big-grass-yellow-box-product-import.html", "boxes/a-big-grass-yellow-box-product-import.html", "301", "1", "0",
                json_encode(['category_id' => (string)$categoryId])],
        ];

        $expectedIndexes = [
            [$categoryId, $product1->id],
            [$categoryId, $product3->id],
            [$newCategoryId, $product3->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product3);
    }

    private function doAsserts(array $expectedRewrites, array $expectedIndexes, Product $product1, Product $product3)
    {
        $productIds = "{$product1->id}, {$product3->id}";

        $actualErrors = [$product1->getErrors(), $product3->getErrors()];

        $this->assertEquals([[], []], $actualErrors);

        $actualRewrites = self::$db->fetchAllNumber("
            SELECT `entity_type`, `request_path`, `target_path`, `redirect_type`, `store_id`, `is_autogenerated`, `metadata` FROM `" . self::$metadata->urlRewriteTable . "`
            WHERE `entity_id` IN ({$productIds})
            ORDER BY `url_rewrite_id`
        ");

        $this->assertEquals($expectedRewrites, $actualRewrites);

        $actualIndexes = self::$db->fetchAllNumber("
            SELECT `category_id`, `product_id` FROM `" . self::$metadata->urlRewriteProductCategoryTable . "`
            WHERE `product_id` IN ({$productIds})
            ORDER BY `url_rewrite_id`
        ");
        $this->assertEquals($expectedIndexes, $actualIndexes);

    }
}