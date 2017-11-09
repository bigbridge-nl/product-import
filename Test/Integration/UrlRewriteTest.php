<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\GeneratedUrlKey;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\Resource\MetaData;
use Magento\Framework\App\ObjectManager;
use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImporterFactory;

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

        list($importer, ) = self::$factory->createImporter($config);

        // product
        $product1 = new SimpleProduct();
        $product1->name = "Big Turquoise Box product-import";
        $product1->sku = '1-product-import';
        $product1->price = "2.75";
        $product1->attribute_set_id = new Reference("Default");
        $product1->category_ids = new References(["Boxes"]);
        $product1->url_key = new GeneratedUrlKey();

        $importer->importSimpleProduct($product1);

        // same sku, different store view
        $product2 = new SimpleProduct();
        $product2->name = "Grote Turquoise Doos product-import";
        $product2->store_view_id = 1;
        $product2->sku = '1-product-import';
#todo remove next 2
        $product2->price = "2.75";
        $product2->attribute_set_id = new Reference("Default");
        $product2->url_key = new GeneratedUrlKey();

        $importer->importSimpleProduct($product2);

        // another product
        $product3 = new SimpleProduct();
        $product3->name = "Big Grass Green Box product-import";
        $product3->sku = '2-product-import';
        $product3->price = "2.65";
        $product3->attribute_set_id = new Reference("Default");
        $product3->category_ids = new References(["Boxes"]);
        $product3->url_key = new GeneratedUrlKey();

        $importer->importSimpleProduct($product3);

        $importer->flush();

        $categoryId = $product1->category_ids[0];

        // insert

        $expectedRewrites = [
#todo "dozen"
            ["product", "grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}", "0", "1", "1", null],
            ["product", "boxes/grote-turquoise-doos-product-import.html", "catalog/product/view/id/{$product1->id}/category/{$categoryId}", "0", "1", "1", serialize(['category_id' => (string)$categoryId])],
            ["product", "big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}", "0", "1", "1", null],
            ["product", "boxes/big-grass-green-box-product-import.html", "catalog/product/view/id/{$product3->id}/category/{$categoryId}", "0", "1", "1", serialize(['category_id' => (string)$categoryId])],
        ];

        $expectedIndexes = [
            [$categoryId, $product1->id],
            [$categoryId, $product3->id],
        ];

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product2, $product3);

        // store again, with no changes

        $importer->importSimpleProduct($product1);
        $importer->importSimpleProduct($product2);
        $importer->importSimpleProduct($product3);

        $importer->flush();

        $this->doAsserts($expectedRewrites, $expectedIndexes, $product1, $product2, $product3);
    }

    private function doAsserts(array $expectedRewrites, array $expectedIndexes, Product $product1, Product $product2, Product $product3)
    {
        $productIds = "{$product1->id}, {$product2->id}, {$product3->id}";

        $actualErrors = [$product1->errors, $product2->errors, $product3->errors];

        $this->assertEquals([[], [], []], $actualErrors);

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