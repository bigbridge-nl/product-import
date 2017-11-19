<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;
use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\Reference\CategoryImporter;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;
use BigBridge\ProductImport\Model\Data\Product;

/**
 * Integration test. It can only be executed from within a shop that has
 *
 * - a attribute set called 'Default'
 * - a store view called 'default'
 *
 * @author Patrick van Bergen
 */
class ImportTest extends \PHPUnit_Framework_TestCase
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

    public function testInsertAndUpdate()
    {
        $success = true;
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallbacks[] = function (Product $product) use (&$success, &$errors) {
            $success = $success && $product->ok;
            $errors = array_merge($errors, $product->errors);
        };

        list($importer, ) = self::$factory->createImporter($config);

        $sku1 = uniqid("bb");
        $sku2 = uniqid("bb");

        $products = [
            ["Big Blue Box", $sku1, 'Default', '3.25', 'admin', [1], 'Taxable Goods'],
            ["Big Yellow Box", $sku2, 'Default', '4.00', 'admin', [1, 2, 999], 'Taxable Goods'],
            ["Grote Gele Doos", $sku2, 'Default', '4.25', 'default', [], 'Taxable Goods'],
        ];

        $product = new SimpleProduct();
        $product->sku = $sku1;
        $product->attribute_set_id = new Reference("Default");
        $product->category_ids = [1];

        $global = $product->global();
        $global->name = "Big Blue Box";
        $global->price = '3.25';

        $importer->importSimpleProduct($product);

        $product = new SimpleProduct();

        $product->sku = $sku2;
        $product->attribute_set_id = new Reference("Default");
        $product->category_ids = [1, 2, 999];

        $global = $product->global();
        $global->name = "Big Yellow Box";
        $global->price = '4.00';

        $default = $product->storeView('default');
        $default->name = "Grote Gele Doos";
        $default->price = '4.25';

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $errors);
        $this->assertTrue($success);

        $product1 = self::$repository->get($sku1);
        $this->assertEquals(4,$product1->getAttributeSetId());
        $this->assertEquals($products[0][0], $product1->getName());
        $this->assertEquals($products[0][3], $product1->getPrice());
        $this->assertEquals([1], $product1->getCategoryIds());

        $product2 = self::$repository->get($sku2, false, 0);
        $this->assertEquals($products[1][0], $product2->getName());
        $this->assertEquals($products[1][3], $product2->getPrice());
        $this->assertEquals([1, 2], $product2->getCategoryIds());

        $product2a = self::$repository->get($sku2, false, 1);
        $this->assertEquals($products[2][0], $product2a->getName());
        $this->assertEquals($products[2][3], $product2a->getPrice());
        $this->assertEquals([1, 2], $product2a->getCategoryIds());



        $products2 = [
            ["Big Blueish Box", $sku1, 'Default', '3.45', 'admin', [1, 2], 'Taxable Goods'],
            ["Big Yellowish Box", $sku2, 'Default', '3.95', 'admin', [], 'Taxable Goods'],
            ["Grote Gelige Doos", $sku2, 'Default', '4.30', 'default', [], 'Taxable Goods'],
        ];

        $product = new SimpleProduct();
        $product->sku = $sku1;
        $product->attribute_set_id = new Reference("Default");
        $product->category_ids = [1, 2];

        $global = $product->global();
        $global->name = "Big Blueish Box";
        $global->price = '3.45';
        $global->tax_class_id = new Reference('Taxable Goods');

        $importer->importSimpleProduct($product);

        $product = new SimpleProduct();

        $product->sku = $sku2;
        $product->attribute_set_id = new Reference("Default");
        $product->category_ids = [];

        $global = $product->global();
        $global->name = "Big Yellowish Box";
        $global->price = '3.95';
        $global->tax_class_id = new Reference('Taxable Goods');

        $default = $product->storeView('default');
        $default->name = "Grote Gelige Doos";
        $default->price = '4.30';
        $default->tax_class_id = new Reference('Taxable Goods');

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $errors);
        $this->assertTrue($success);

        $product1 = self::$repository->get($sku1, false, 0, true);
        $this->assertEquals($products2[0][0], $product1->getName());
        $this->assertEquals($products2[0][3], $product1->getPrice());
        $this->assertEquals([1, 2], $product1->getCategoryIds());
        $this->assertEquals(2, $product1->getTaxClassId());

        $product2 = self::$repository->get($sku2, false, 0, true);
        $this->assertEquals($products2[1][0], $product2->getName());
        $this->assertEquals($products2[1][3], $product2->getPrice());
        $this->assertEquals([1, 2], $product2->getCategoryIds());

        $product2a = self::$repository->get($sku2, false, 1, true);
        $this->assertEquals($products2[2][0], $product2a->getName());
        $this->assertEquals($products2[2][3], $product2a->getPrice());
    }

    public function testUrlRewrites()
    {
        /** @var Magento2DbConnection $db */
        $db = ObjectManager::getInstance()->get(Magento2DbConnection::class);
        /** @var MetaData $metadata */
        $metadata = ObjectManager::getInstance()->get(MetaData::class);
        /** @var CategoryImporter $categoryImporter */
        $categoryImporter = ObjectManager::getInstance()->get(CategoryImporter::class);
        list($c1,) = $categoryImporter->importCategoryPath("Boxes", true);
        list($c2a,) = $categoryImporter->importCategoryPath("Colored Things", true);
        list($c2b,) = $categoryImporter->importCategoryPath("Colored Things/Containers", true);
        list($c2c,) = $categoryImporter->importCategoryPath("Colored Things/Containers/Large", true);

        $config = new ImportConfig();
        $config->magentoVersion = '2.1.8';

        list($importer, ) = self::$factory->createImporter($config);

        $sku1 = uniqid("bb");
        $urlKey = 'u' . $sku1;

        $product = new SimpleProduct();
        $product->sku = $sku1;
        $product->attribute_set_id = new Reference("Default");
        $product->category_ids = new References(["Boxes", "Colored Things/Containers/Large"]);

        $global = $product->global();
        $global->name = "Big Purple Box";
        $global->price = "1.25";
        $global->url_key = $urlKey;

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $product->errors);

        $actual = $db->fetchAllNumber("
            SELECT `entity_type`, `request_path`, `target_path`, `redirect_type`, `store_id`, `is_autogenerated`, `metadata` FROM `{$metadata->urlRewriteTable}` 
            WHERE `entity_id` = {$product->id}
            ORDER BY `url_rewrite_id`
        ");

        $expected = [
            ['product', $urlKey . '.html', 'catalog/product/view/id/' . $product->id, '0', '1', '1', null],
            ['product', 'boxes/' . $urlKey . '.html', 'catalog/product/view/id/' . $product->id . '/category/' . $c1, '0', '1', '1', serialize(['category_id' => (string)$c1])],
            ['product', 'colored-things/' . $urlKey . '.html', 'catalog/product/view/id/' . $product->id . '/category/' . $c2a, '0', '1', '1', serialize(['category_id' => (string)$c2a])],
            ['product', 'colored-things/containers/' . $urlKey . '.html', 'catalog/product/view/id/' . $product->id . '/category/' . $c2b, '0', '1', '1', serialize(['category_id' => (string)$c2b])],
            ['product', 'colored-things/containers/large/' . $urlKey . '.html', 'catalog/product/view/id/' . $product->id . '/category/' . $c2c, '0', '1', '1', serialize(['category_id' => (string)$c2c])],
        ];

        $this->assertEquals($expected, $actual);
    }

    public function testErrors()
    {
        $config = new ImportConfig();

        list($importer, $error) = self::$factory->createImporter($config);

        $product = new SimpleProduct();
        $product->attribute_set_id = new Reference("Checkers");

        $importer->importSimpleProduct($product);

        $importer->flush();

        $expectedErrors = [
            "attribute set name not found: Checkers",
            "Product has no global values. Please specify global()",
            "missing sku",
            "missing attribute set id",
        ];

        $this->assertEquals($expectedErrors, $product->errors);
        $this->assertFalse($product->ok);
    }

    public function testResultCallback()
    {
        $log = "";
        $lastId = null;

        $config = new ImportConfig();
        $config->resultCallbacks[] = function(Product $product) use (&$log, &$lastId) {

            if ($product->ok) {
                $log .= sprintf("%s: success! sku = %s, id = %s\n", $product->lineNumber, $product->sku, $product->id);
                $lastId = $product->id;
            } else {
                $log .= sprintf("%s: failed! error = %s\n", $product->lineNumber, implode('; ', $product->errors));
            }

        };

        list($importer, ) = self::$factory->createImporter($config);

        $lines = [
            ['Purple Box', "", "3.95"],
            ['Yellow Box', uniqid('bb'), "2.95"]
        ];

        foreach ($lines as $i => $line) {

            $product = new SimpleProduct();
            $product->sku = $line[1];
            $product->attribute_set_id = new Reference("Default");
            $product->lineNumber = $i + 1;

            $global = $product->global();
            $global->name = $line[0];
            $global->price = $line[2];

            $importer->importSimpleProduct($product);
        }

        $importer->flush();

        $this->assertEquals("1: failed! error = missing sku\n2: success! sku = {$lines[1][1]}, id = {$lastId}\n", $log);
    }

    public function testCreateCategories()
    {
        $success = true;

        $config = new ImportConfig();
        $config->resultCallbacks[] = function(Product $product) use (&$success) {
            $success = $success && $product->ok;
        };

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = new SimpleProduct();
        $product1->name = "Pine trees";
        $product1->attribute_set_id = new Reference("Default");
        $product1->category_ids = new References(['Chairs', 'Tables', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $global = $product1->global();
        $global->sku = uniqid('bb');
        $global->price = '399.95';

        $importer->importSimpleProduct($product1);

        $product2 = new SimpleProduct();
        $product2->name = "Oak trees";
        $product2->attribute_set_id = new Reference("Default");
        $product2->category_ids = new References(['Chairs', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $global = $product2->global();
        $global->sku = uniqid('bb');;
        $global->price = '449.95';

        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(4, count(array_unique($product1->category_ids)));
        $this->assertEquals(3, count(array_unique($product2->category_ids)));
        $this->assertEquals(1, count(array_diff($product1->category_ids, $product2->category_ids)));
    }

    public function testMissingCategories()
    {
        $success = true;

        $config = new ImportConfig();

        // the essence of this test
        $config->autoCreateCategories = false;

        $config->resultCallbacks[] = function(Product $product) use (&$success) {
            $success = $success && $product->ok;
        };

        list($importer, ) = self::$factory->createImporter($config);

        $product1 = new SimpleProduct();
        $product1->sku = "gummybears";
        $product1->attribute_set_id = new Reference("Default");
        $product1->category_ids = new References(['Gummybears', 'Other Candy', 'German Candy']);
        $global = $product1->global();
        $global->name = "Gummybears";
        $global->price = '1.99';

        $importer->importSimpleProduct($product1);

        $importer->flush();

        $this->assertEquals(0, count($product1->category_ids));
        $this->assertEquals(["category not found: Gummybears"], $product1->errors);
        $this->assertEquals(false, $product1->ok);
        $this->assertEquals(false, $success);
    }
}
