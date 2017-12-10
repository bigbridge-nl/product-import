<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\ProductStoreView;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\Resolver\CategoryImporter;
use BigBridge\ProductImport\Api\SimpleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\Product;
use Magento\Framework\Exception\NoSuchEntityException;

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

    /** @var  Magento2DbConnection */
    protected static $db;

    /** @var  Metadata */
    protected static $metaData;

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

        self::$metaData = ObjectManager::getInstance()->get(MetaData::class);

        $table = self::$metaData->productEntityTable;
        self::$db->execute("DELETE FROM `{$table}` WHERE sku LIKE '%-product-import'");
    }

    public function testInsertAndUpdate()
    {
        $success = true;
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallbacks[] = function (Product $product) use (&$success, &$errors) {
            $success = $success && $product->isOk();
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $sku1 = uniqid("bb");
        $sku2 = uniqid("bb");

        $products = [
            ["Big Blue Box", $sku1, 'Default', '3.25', 'admin', [1], 'Taxable Goods'],
            ["Big Yellow Box", $sku2, 'Default', '4.00', 'admin', [1, 2, 999], 'Taxable Goods'],
            ["Grote Gele Doos", $sku2, 'Default', '4.25', 'default', [], 'Taxable Goods'],
        ];

        $product = new SimpleProduct($sku1);
        $product->setAttributeSetByName("Default");
        $product->setCategoryIds([1]);

        $global = $product->global();
        $global->setName("Big Blue Box");
        $global->setPrice('3.25');

        $importer->importSimpleProduct($product);

        $product = new SimpleProduct($sku2);

        $product->setAttributeSetByName("Default");
        $product->setCategoryIds([1, 2, 999]);

        $global = $product->global();
        $global->setName("Big Yellow Box");
        $global->setPrice('4.00');

        $default = $product->storeView('default');
        $default->setName("Grote Gele Doos");
        $default->setPrice('4.25');

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

        $product = new SimpleProduct($sku1);

        $product->setAttributeSetByName("Default");
        $product->setCategoryIds([1, 2]);

        $global = $product->global();
        $global->setName("Big Blueish Box");
        $global->setPrice('3.45');
        $global->setTaxClassName('Taxable Goods');

        $importer->importSimpleProduct($product);

        $product = new SimpleProduct($sku2);

        $product->setAttributeSetByName("Default");
        $product->setCategoryIds([]);

        $global = $product->global();
        $global->setName("Big Yellowish Box");
        $global->setPrice('3.95');
        $global->setTaxClassName('Taxable Goods');

        $default = $product->storeView('default');
        $default->setName("Grote Gelige Doos");
        $default->setPrice('4.30');
        $default->setTaxClassName('Taxable Goods');

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $errors);
        $this->assertTrue($success);

        try {
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
        } catch (NoSuchEntityException $e) {
            $this->assertTrue(false);
        }
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

        $importer = self::$factory->createImporter($config);

        $sku1 = uniqid("bb");
        $urlKey = 'u' . $sku1;

        $product = new SimpleProduct($sku1);

        $product->setAttributeSetByName("Default");
        $product->setCategoriesByGlobalName(["Boxes", "Colored Things/Containers/Large"]);

        $global = $product->global();
        $global->setName("Big Purple Box");
        $global->setPrice("1.25");
        $global->setUrlKey($urlKey);

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $product->getErrors());

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

        $importer = self::$factory->createImporter($config);

        $product = new SimpleProduct("tiny-blue-dot");
        $product->setAttributeSetByName("Checkers");

        $importer->importSimpleProduct($product);

        $importer->flush();

        $expectedErrors = [
            "attribute set name not found: Checkers",
            "missing attribute set id",
            "missing name",
            "missing price"
        ];

        $this->assertEquals($expectedErrors, $product->getErrors());
        $this->assertFalse($product->isOk());
    }

    public function testResultCallback()
    {
        $log = "";
        $lastId = null;

        $config = new ImportConfig();
        $config->resultCallbacks[] = function(Product $product) use (&$log, &$lastId) {

            if ($product->isOk()) {
                $log .= sprintf("%s: success! sku = %s, id = %s\n", $product->lineNumber, $product->getSku(), $product->id);
                $lastId = $product->id;
            } else {
                $log .= sprintf("%s: failed! error = %s\n", $product->lineNumber, implode('; ', $product->getErrors()));
            }

        };

        $importer = self::$factory->createImporter($config);

        $lines = [
            ['Purple Box', "", "3.95"],
            ['Yellow Box', uniqid('bb'), "2.95"]
        ];

        foreach ($lines as $i => $line) {

            $product = new SimpleProduct($line[1]);

            $product->setAttributeSetByName("Default");
            $product->lineNumber = $i + 1;

            $global = $product->global();
            $global->setName($line[0]);
            $global->setPrice($line[2]);

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
            $success = $success && $product->isOk();
        };

        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct(uniqid('bb'));
        $product1->setAttributeSetByName("Default");
        $product1->setCategoriesByGlobalName(['Chairs', 'Tables', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $global = $product1->global();
        $global->setName("Pine trees");
        $global->setPrice('399.95');

        $importer->importSimpleProduct($product1);

        $product2 = new SimpleProduct(uniqid('bb'));
        $product2->setAttributeSetByName("Default");
        $product2->setCategoriesByGlobalName(['Chairs', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $global = $product2->global();
        $global->setName("Oak trees");
        $global->setPrice('449.95');

        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(4, count(array_unique($product1->getCategoryIds())));
        $this->assertEquals(3, count(array_unique($product2->getCategoryIds())));
        $this->assertEquals(1, count(array_diff($product1->getCategoryIds(), $product2->getCategoryIds())));
    }

    public function testWebsites()
    {
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallbacks[] = function(Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct(uniqid('bb'));
        $product1->setAttributeSetByName("Default");
        $product1->setWebsitesByCode(['base']);
        $global = $product1->global();
        $global->setName("Book voucher");
        $global->setPrice('25.00');

        $importer->importSimpleProduct($product1);

        $importer->flush();

        $this->assertEquals([], $errors);

        $websiteIds = self::$db->fetchAllNumber("
            SELECT `product_id`, `website_id` FROM `" . self::$metaData->productWebsiteTable . "`
            WHERE `product_id` = {$product1->id}
        ");

        $this->assertEquals([[$product1->id, 1]], $websiteIds);
    }

    public function testMissingCategories()
    {
        $success = true;

        $config = new ImportConfig();

        // the essence of this test
        $config->autoCreateCategories = false;

        $config->resultCallbacks[] = function(Product $product) use (&$success) {
            $success = $success && $product->isOk();
        };

        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("gummybears");
        $product1->setAttributeSetByName("Default");
        $product1->setCategoriesByGlobalName(['Gummybears', 'Other Candy', 'German Candy']);
        $global = $product1->global();
        $global->setName("Gummybears");
        $global->setPrice('1.99');

        $importer->importSimpleProduct($product1);

        $importer->flush();

        $this->assertEquals(0, count($product1->getCategoryIds()));
        $this->assertEquals(["category not found: Gummybears"], $product1->getErrors());
        $this->assertEquals(false, $product1->isOk());
        $this->assertEquals(false, $success);
    }

    public function testDefaults()
    {
        $errors = [];

        $config = new ImportConfig();

        $config->resultCallbacks[] = function(Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        // new

        $product1 = new SimpleProduct("loafers-product-import");
        $global = $product1->global();
        $global->setName("Loafers");
        $global->setPrice('9.19');

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals([], $errors);

        $attributeSetId = $product1->getAttributeSetId();
        $taxClassId = $product1->global()->getAttribute(ProductStoreView::ATTR_TAX_CLASS_ID);
        $visibility = ProductStoreView::VISIBILITY_BOTH;
        $status = ProductStoreView::STATUS_DISABLED;

        $this->checkProductValues($attributeSetId, $taxClassId, $visibility, $status);

        // update not changing values

        $product2 = new SimpleProduct("loafers-product-import");

        $importer->importSimpleProduct($product2);
        $importer->flush();

        $this->assertEquals([], $errors);
        $this->checkProductValues($attributeSetId, $taxClassId, $visibility, $status);

        // update changing values

        $newVisibility = ProductStoreView::VISIBILITY_IN_SEARCH;
        $newStatus = ProductStoreView::STATUS_ENABLED;

        $product3 = new SimpleProduct("loafers-product-import");
        $product3->setAttributeSetId(5);
        $product3->global()->setTaxClassName("Retail Customer");
        $product3->global()->setVisibility($newVisibility);
        $product3->global()->setStatus($newStatus);

        $importer->importSimpleProduct($product3);
        $importer->flush();

        $newAttributeSetId = $product3->getAttributeSetId();
        $newTaxClassId = $product3->global()->getAttribute(ProductStoreView::ATTR_TAX_CLASS_ID);

        $this->assertEquals([], $errors);
        $this->checkProductValues($newAttributeSetId, $newTaxClassId, $newVisibility, $newStatus);

        // update not changing values (check if defaults were not restored)

        $product3 = new SimpleProduct("loafers-product-import");

        $importer->importSimpleProduct($product3);
        $importer->flush();

        $this->assertEquals([], $errors);
        $this->checkProductValues($newAttributeSetId, $newTaxClassId, $newVisibility, $newStatus);
    }

    private function checkProductValues($attributeSetId, $taxClassId, $visibility, $status)
    {
        try {
            $productS = self::$repository->get("loafers-product-import", false, 0, true);
        } catch (NoSuchEntityException $e) {
            $this->assertTrue(false);
        }

        $this->assertEquals($attributeSetId, $productS->getAttributeSetId());
        $this->assertEquals($taxClassId, $productS->getTaxClassId());
        $this->assertEquals($visibility, $productS->getVisibility());
        $this->assertEquals($status, $productS->getStatus());
    }

    public function testImages()
    {
        @unlink(BP . '/pub/media/catalog/product/d/u/duck1.jpg');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck2.png');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck3.png');

        $errors = [];

        $config = new ImportConfig();

        $config->resultCallbacks[] = function(Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        // use 2 images for 3 roles

        $product1 = new SimpleProduct("ducky1-product-import");
        $global = $product1->global();
        $global->setName("Ducky 1");
        $global->setPrice('1.00');

        $image = $product1->addImage(__DIR__ . '/../images/duck1.jpg', true);
        $product1->global()->setImageGalleryInformation($image, "First duck", 1, true);
        $product1->global()->setImageRole($image, ProductStoreView::THUMBNAIL_IMAGE);

        $image = $product1->addImage(__DIR__ . '/../images/duck2.png', false);
        $product1->global()->setImageGalleryInformation($image, "Second duck", 2, false);
        $product1->global()->setImageRole($image, ProductStoreView::BASE_IMAGE);
        $product1->storeView('default')->setImageGalleryInformation($image, "Tweede eend", 3, false);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $attributeId = self::$metaData->mediaGalleryAttributeId;

        $this->assertEquals([], $errors);
        $this->assertTrue(file_exists(BP . '/pub/media/catalog/product/d/u/duck1.jpg'));
        $this->assertTrue(file_exists(BP . '/pub/media/catalog/product/d/u/duck2.png'));

        $media = [
            [$attributeId, '/d/u/duck1.jpg', 'image', 0],
            [$attributeId, '/d/u/duck2.png', 'image', 1],
        ];

        $values = [
            ['0', $product1->id, 'First duck', '1', '0'],
            ['0', $product1->id, 'Second duck', '2', '1'],
            ['1', $product1->id, 'Tweede eend', '3', '1'],
        ];

        $this->checkImageData($product1, $media, $values);

        $productS = self::$repository->get("ducky1-product-import");
        $this->assertEquals('/d/u/duck1.jpg', $productS->getThumbnailImage());
        $this->assertEquals('/d/u/duck2.png', $productS->getImage());

        // add 2 other/same images
        // change disabled of one image
        // second image already in use

        link(__DIR__ . '/../images/duck3.png', BP . '/pub/media/catalog/product/d/u/duck3.png');

        $product2 = new SimpleProduct("ducky1-product-import");
        $global = $product2->global();
        $global->setName("Ducky 1");
        $global->setPrice('1.00');

        $image = $product2->addImage(__DIR__ . '/../images/duck2.png', true);
        $product2->global()->setImageGalleryInformation($image, "Second duck", 2, false);
        $product2->global()->setImageRole($image, ProductStoreView::BASE_IMAGE);
        $product2->storeView('default')->setImageGalleryInformation($image, "Tweede eendje", 3, false);

        $image = $product1->addImage('/../images/duck3.png', false);
        $product2->global()->setImageGalleryInformation($image, "Second duck", 3, true);
        $product2->global()->setImageRole($image, ProductStoreView::SMALL_IMAGE);
        $product2->storeView('default')->setImageGalleryInformation($image, "Derde eendje", 3, false);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $attributeId = self::$metaData->mediaGalleryAttributeId;

        $this->assertEquals([], $errors);
        $this->assertTrue(file_exists(BP . '/pub/media/catalog/product/d/u/ducky3.png'));

        $media = [
            [$attributeId, '/d/u/duck1.jpg', 'image', 0],
            [$attributeId, '/d/u/duck2.png', 'image', 0],
            [$attributeId, '/d/u/duck3_1.png', 'image', 0],
        ];

        $values = [
            ['0', $product1->id, 'First ducky', '1', '0'],
            ['0', $product1->id, 'Second ducky', '2', '1'],
            ['1', $product1->id, 'Tweede eendje', '3', '1'],
            ['0', $product1->id, 'Third ducky', '2', '1'],
            ['1', $product1->id, 'Derde eendje', '3', '1'],
        ];

        $this->checkImageData($product1, $media, $values);

        $productS = self::$repository->get("ducky1-product-import");
        $this->assertEquals('/d/u/duck1.jpg', $productS->getThumbnailImage());
        $this->assertEquals('/d/u/duck2.png', $productS->getImage());
        $this->assertEquals('/d/u/duck3_1.png', $productS->getSmallImage());

        // update, no changes (important be we now have ducky3_1.png, it should stay that way)

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->checkImageData($product1, $media, $values);
    }

    private function checkImageData($product, $mediaData, $valueData)
    {
        $toEntity = self::$metaData->mediaGalleryValueToEntityTable;
        $media = self::$metaData->mediaGalleryTable;
        $value = self::$metaData->mediaGalleryValueTable;

        $valueIds = self::$db->fetchSingleColumn("
            SELECT value_id 
            FROM {$toEntity}
            WHERE `entity_id` = " . $product->id . "
            ORDER BY value_id
        ");
        $this->assertEquals(2, count($valueIds));

        $results = self::$db->fetchAllNumber("
            SELECT attribute_id, value, media_type, disabled
            FROM {$media}
            WHERE value_id IN (" . implode(',', $valueIds) . ")
            ORDER BY value_id
        ");

        $this->assertEquals($mediaData, $results);

        $results = self::$db->fetchAllNumber("
            SELECT store_id, entity_id, label, position, disabled
            FROM {$value}
            WHERE value_id IN (" . implode(',', $valueIds) . ")
            ORDER BY value_id            
        ");

        $this->assertEquals($valueData, $results);
    }
}
