<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\Data\BundleProductSelection;
use BigBridge\ProductImport\Api\Data\CustomOptionValue;
use BigBridge\ProductImport\Api\Data\ProductStockItem;
use BigBridge\ProductImport\Api\Importer;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\BundleProductOption;
use BigBridge\ProductImport\Api\Data\BundleProductStoreView;
use BigBridge\ProductImport\Api\Data\CustomOption;
use BigBridge\ProductImport\Api\Data\DownloadLink;
use BigBridge\ProductImport\Api\Data\DownloadSample;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\TierPrice;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\GroupedProductMember;
use Exception;

/**
 * Integration test. It can only be executed from within a shop that has
 *
 * - a attribute set called 'Default'
 * - a store view called 'default'
 *
 * @author Patrick van Bergen
 */
class ImportTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /** @var  ImporterFactory */
    private static $factory;

    /** @var ProductRepositoryInterface $repository */
    private static $repository;

    /** @var  Magento2DbConnection */
    protected static $db;

    /** @var  Metadata */
    protected static $metaData;

    public static function setUpBeforeClass(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);

        /** @var ProductRepositoryInterface $repository */
        self::$repository = $objectManager->get(ProductRepositoryInterface::class);

        /** @var Magento2DbConnection $db */
        self::$db = $objectManager->get(Magento2DbConnection::class);

        self::$metaData = $objectManager->get(MetaData::class);

        $table = self::$metaData->productEntityTable;
        self::$db->execute("DELETE FROM `{$table}` WHERE sku LIKE '%-product-import'");

        // remove the multiple select attribute
        self::$db->execute("
            DELETE FROM " . self::$metaData->attributeTable . "
            WHERE attribute_code = 'color_group_product_importer'
        ");

        // create a multiple select attribute
        self::$db->execute("
            INSERT INTO " . self::$metaData->attributeTable . "
            SET
                entity_type_id = " . self::$metaData->productEntityTypeId . ",
                attribute_code = 'color_group_product_importer',
                frontend_input = 'multiselect',
                backend_type = 'varchar'
        ");

        $insertId = self::$db->getLastInsertId();

        self::$db->execute("
            INSERT INTO " . self::$metaData->catalogAttributeTable . "
            SET
                attribute_id = " . $insertId . ",
                is_global = 1
        ");

        self::$metaData->productEavAttributeInfo['color_group_product_importer'] = new EavAttributeInfo('color_group_product_importer', $insertId, false, 'varchar', 'catalog_product_entity_varchar', 'multiselect', 1);
    }

    public static function tearDownAfterClass(): void
    {
        // remove the multiple select attribute
        self::$db->execute("
            DELETE FROM " . self::$metaData->attributeTable . "
            WHERE attribute_code = 'color_group_product_importer'
        ");
    }

    /**
     * @throws Exception
     * @throws NoSuchEntityException
     */
    public function testInsertAndUpdate()
    {
        $success = true;
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$success, &$errors) {
            $success = $success && $product->isOk();
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $sku1 = uniqid("bb");
        $sku2 = uniqid("bb");

        $products = [
            ["Big Blue Box", $sku1, 'Default', '3.25', 'admin', [1], 'Taxable Goods'],
            ["Big Yellow Box", $sku2, 'Default', '4.00', 'admin', [1, 2, 99999], 'Taxable Goods'],
            ["Grote Gele Doos", $sku2, 'Default', '4.25', 'default', [], 'Taxable Goods'],
        ];

        $product = new SimpleProduct($sku1);
        $product->setAttributeSetByName("Default");
        $product->addCategoryIds([1]);

        $global = $product->global();
        $global->setName("Big Blue Box");
        $global->setPrice('3.25');

        $importer->importSimpleProduct($product);

        $product = new SimpleProduct($sku2);
        $product->setAttributeSetByName("Default");

        $product->setAttributeSetByName("Default");
        $product->addCategoryIds([1, 2, 99999]);

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
        $this->assertEquals(4, $product1->getAttributeSetId());
        $this->assertEquals($products[0][0], $product1->getName());
        $this->assertEquals((float)$products[0][3], (float)$product1->getPrice());
        $this->assertEquals([1], $product1->getCategoryIds());

        $product2 = self::$repository->get($sku2, false, 0);
        $this->assertEquals($products[1][0], $product2->getName());
        $this->assertEquals((float)$products[1][3], (float)$product2->getPrice());
        $this->assertEquals([1, 2], $product2->getCategoryIds());

        $product2a = self::$repository->get($sku2, false, 1);
        $this->assertEquals($products[2][0], $product2a->getName());
        $this->assertEquals((float)$products[2][3], (float)$product2a->getPrice());
        $this->assertEquals([1, 2], $product2a->getCategoryIds());


        $products2 = [
            ["Big Blueish Box", $sku1, 'Default', '3.45', 'admin', [1, 2], 'Taxable Goods'],
            ["Big Yellowish Box", $sku2, 'Default', '3.95', 'admin', [], 'Taxable Goods'],
            ["Grote Gelige Doos", $sku2, 'Default', '4.30', 'default', [], 'Taxable Goods'],
        ];

        $product = new SimpleProduct($sku1);
        $product->setAttributeSetByName("Default");

        $product->setAttributeSetByName("Default");
        $product->addCategoryIds([1, 2]);

        $global = $product->global();
        $global->setName("Big Blueish Box");
        $global->setPrice('3.45');
        $global->setTaxClassName('Taxable Goods');

        $importer->importSimpleProduct($product);

        $product = new SimpleProduct($sku2);
        $product->setAttributeSetByName("Default");
        $product->addCategoryIds([]);

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
            $this->assertEquals((float)$products2[0][3], (float)$product1->getPrice());
            $this->assertEquals([1, 2], $product1->getCategoryIds());
            $this->assertEquals(2, $product1->getTaxClassId());

            $product2 = self::$repository->get($sku2, false, 0, true);
            $this->assertEquals($products2[1][0], $product2->getName());
            $this->assertEquals((float)$products2[1][3], (float)$product2->getPrice());
            $this->assertEquals([1, 2], $product2->getCategoryIds());

            $product2a = self::$repository->get($sku2, false, 1, true);
            $this->assertEquals($products2[2][0], $product2a->getName());
            $this->assertEquals((float)$products2[2][3], (float)$product2a->getPrice());
        } catch (NoSuchEntityException $e) {
            $this->assertTrue(false);
        }
    }

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
    public function testResultCallback()
    {
        $log = "";
        $lastId = null;

        $config = new ImportConfig();
        $config->resultCallback = function(Product $product) use (&$log, &$lastId) {

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

    /**
     * @throws Exception
     */
    public function testCreateCategories()
    {
        $success = true;

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$success) {
            $success = $success && $product->isOk();
        };

        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct(uniqid('bb'));
        $product1->setAttributeSetByName("Default");
        $product1->addCategoriesByGlobalName(['Chairs', 'Tables', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $global = $product1->global();
        $global->setName("Pine trees");
        $global->setPrice('399.95');

        $importer->importSimpleProduct($product1);

        $product2 = new SimpleProduct(uniqid('bb'));
        $product2->setAttributeSetByName("Default");
        $product2->addCategoriesByGlobalName(['Chairs', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $global = $product2->global();
        $global->setName("Oak trees");
        $global->setPrice('449.95');

        $importer->importSimpleProduct($product2);

        $importer->flush();

        $this->assertEquals(4, count(array_unique($product1->getCategoryIds())));
        $this->assertEquals(3, count(array_unique($product2->getCategoryIds())));
        $this->assertEquals(1, count(array_diff($product1->getCategoryIds(), $product2->getCategoryIds())));
    }


    /**
     * @throws Exception
     */
    public function testRemoveCategoryLinks()
    {
        $success = true;

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$success) {
            $success = $success && $product->isOk();
        };

        $importer = self::$factory->createImporter($config);

        $sku = uniqid('bb');

        $product1 = new SimpleProduct($sku);
        $product1->setAttributeSetByName("Default");
        $product1->addCategoriesByGlobalName(['Chairs', 'Tables', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $global = $product1->global();
        $global->setName("Pine trees");
        $global->setPrice('399.95');

        // store product with 4 categories
        $importer->importSimpleProduct($product1);
        $importer->flush();
        $this->assertEquals(4, $this->getCatCount($product1->id));

        // remove a category: should not be removed in database
        $product1->addCategoriesByGlobalName(['Chairs', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $importer->importSimpleProduct($product1);
        $importer->flush();
        $this->assertEquals(4, $this->getCatCount($product1->id));

        // use 'set'
        $config = new ImportConfig();
        $config->categoryStrategy = ImportConfig::CATEGORY_STRATEGY_SET;
        $importer = self::$factory->createImporter($config);

        // name no categories: do not remove any categories
        $product1 = new SimpleProduct($sku);
        $global = $product1->global();
        $global->setName("Pine trees");
        $global->setPrice('399.95');
        $importer->importSimpleProduct($product1);
        $importer->flush();
        $this->assertEquals(4, $this->getCatCount($product1->id));

        // name 3 categories: remove 1
        $product1->addCategoriesByGlobalName(['Chairs', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);
        $importer->importSimpleProduct($product1);
        $importer->flush();
        $this->assertEquals(3, $this->getCatCount($product1->id));
    }

    protected function getCatCount($productId)
    {
        return self::$db->fetchSingleCell("
            SELECT count(*)
            FROM " . self::$metaData->categoryProductTable . "
            WHERE `product_id` = " . $productId . "
        ");
    }

    /**
     * @throws Exception
     */
    public function testWebsites()
    {
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$errors) {
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

        $websiteIds = self::$db->fetchAllNonAssoc("
            SELECT `product_id`, `website_id` FROM `" . self::$metaData->productWebsiteTable . "`
            WHERE `product_id` = {$product1->id}
        ");

        $this->assertEquals([[$product1->id, 1]], $websiteIds);
    }

    /**
     * @throws Exception
     */
    public function testMissingCategories()
    {
        $success = true;

        $config = new ImportConfig();

        // the essence of this test
        $config->autoCreateCategories = false;

        $config->resultCallback = function (Product $product) use (&$success) {
            $success = $success && $product->isOk();
        };

        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("gummybears");
        $product1->setAttributeSetByName("Default");
        $product1->addCategoriesByGlobalName(['Gummybears', 'Other Candy', 'German Candy']);
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

    /**
     * @throws Exception
     * @throws NoSuchEntityException
     */
    public function testImages()
    {
        @unlink(BP . '/pub/media/catalog/product/d/u/duck1.jpg');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck1_1.jpg');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck2.png');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck3.png');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck3_1.png');

        $errors = [];

        $config = new ImportConfig();

        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        // use 2 images with 2 roles

        $product1 = new SimpleProduct("ducky1-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Ducky 1");
        $global->setPrice('1.00');

        $image = $product1->addImage(__DIR__ . '/../images/duck1.jpg');
        $product1->global()->setImageGalleryInformation($image, "First duck", 1, true);
        $product1->global()->setImageRole($image, ProductStoreView::THUMBNAIL_IMAGE);

        $image = $product1->addImage(__DIR__ . '/../images/duck2.png');
        $image->disable();
        $product1->global()->setImageGalleryInformation($image, "Second duck", 2, false);
        $product1->global()->setImageRole($image, ProductStoreView::BASE_IMAGE);
        $product1->storeView('default')->setImageGalleryInformation($image, "Tweede eend", 3, false);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $attributeId = self::$metaData->mediaGalleryAttributeId;

        $this->assertEquals([], $errors);
        $this->assertTrue(file_exists(BP . '/pub/media/catalog/product/d/u/duck1.jpg'));
        $this->assertFalse(file_exists(BP . '/pub/media/catalog/product/d/u/duck1_1.jpg'));
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

        $productS = self::$repository->get("ducky1-product-import", false, 0, true);
        $this->assertEquals('/d/u/duck1.jpg', $productS->getThumbnail());
        $this->assertEquals('/d/u/duck2.png', $productS->getImage());

        // add 2 other/same images
        // change disabled of one image
        // second image already in use

        link(__DIR__ . '/../images/duck3.png', BP . '/pub/media/catalog/product/d/u/duck3.png');

        $product2 = new SimpleProduct("ducky1-product-import");
        $product2->setAttributeSetByName("Default");
        $global = $product2->global();
        $global->setName("Ducky 1");
        $global->setPrice('1.00');

        $image = $product2->addImage(__DIR__ . '/../images/duck2.png');
        $product2->global()->setImageGalleryInformation($image, "Second duck", 2, false);
        $product2->global()->setImageRole($image, ProductStoreView::BASE_IMAGE);
        $product2->storeView('default')->setImageGalleryInformation($image, "Tweede eendje", 3, false);

        $image = $product2->addImage(__DIR__ . '/../images/duck3.png');
        $image->disable();
        $product2->global()->setImageGalleryInformation($image, "Third duck", 3, true);
        $product2->global()->setImageRole($image, ProductStoreView::SMALL_IMAGE);
        $product2->storeView('default')->setImageGalleryInformation($image, "Derde eendje", 3, false);

        $importer->importSimpleProduct($product2);
        $importer->flush();

        $attributeId = self::$metaData->mediaGalleryAttributeId;

        $this->assertEquals([], $errors);
        $this->assertTrue(file_exists(BP . '/pub/media/catalog/product/d/u/duck3_1.png'));

        $media = [
            [$attributeId, '/d/u/duck1.jpg', 'image', '0'],
            [$attributeId, '/d/u/duck2.png', 'image', '0'],
            [$attributeId, '/d/u/duck3_1.png', 'image', '1'],
        ];

        $values = [
            ['0', $product2->id, 'First duck', '1', '0'],
            ['0', $product2->id, 'Second duck', '2', '1'],
            ['1', $product2->id, 'Tweede eendje', '3', '1'],
            ['0', $product2->id, 'Third duck', '3', '0'],
            ['1', $product2->id, 'Derde eendje', '3', '1'],
        ];

        $this->checkImageData($product2, $media, $values);

        $productS = self::$repository->get("ducky1-product-import", false, 0, true);
        $this->assertEquals('/d/u/duck1.jpg', $productS->getThumbnail());
        $this->assertFalse(file_exists(BP . '/pub/media/catalog/product/d/u/duck1_1.jpg'));
        $this->assertEquals('/d/u/duck2.png', $productS->getImage());
        $this->assertEquals('/d/u/duck3_1.png', $productS->getSmallImage());

        // update, no changes (important be we now have ducky3_1.png, it should stay that way)

        $importer->importSimpleProduct($product2);
        $importer->flush();

        $this->checkImageData($product2, $media, $values);

        // remove two images
        // image strategy: set

        $config = new ImportConfig();
        $config->imageStrategy = ImportConfig::IMAGE_STRATEGY_SET;

        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $product3 = new SimpleProduct("ducky1-product-import");
        $product3->setAttributeSetByName("Default");
        $global = $product3->global();
        $global->setName("Ducky 1");
        $global->setPrice('1.00');

        $image = $product3->addImage(__DIR__ . '/../images/duck1.jpg');
        $product3->global()->setImageGalleryInformation($image, "First duck", 1, true);
        $product3->global()->setImageRole($image, ProductStoreView::THUMBNAIL_IMAGE);

        $importer->importSimpleProduct($product3);
        $importer->flush();

        $attributeId = self::$metaData->mediaGalleryAttributeId;

        $this->assertEquals([], $errors);
        $this->assertTrue(file_exists(BP . '/pub/media/catalog/product/d/u/duck1.jpg'));
        $this->assertFalse(file_exists(BP . '/pub/media/catalog/product/d/u/duck1_1.jpg'));
        $this->assertFalse(file_exists(BP . '/pub/media/catalog/product/d/u/duck2.png'));

        $media = [
            [$attributeId, '/d/u/duck1.jpg', 'image', '0'],
        ];

        $values = [
            ['0', $product3->id, 'First duck', '1', '0']
        ];

        $this->checkImageData($product3, $media, $values);

        $productS = self::$repository->get("ducky1-product-import", false, 0, true);
        $this->assertEquals('/d/u/duck1.jpg', $productS->getThumbnail());
        $this->assertEquals(null, $productS->getImage());
        $this->assertEquals(null, $productS->getSmallImage());

        // no images? do not remove images

        $product4 = new SimpleProduct("ducky1-product-import");
        $product4->setAttributeSetByName("Default");
        $global = $product4->global();
        $global->setName("Ducky 1");
        $global->setPrice('1.00');

        $importer->importSimpleProduct($product4);
        $importer->flush();

        $attributeId = self::$metaData->mediaGalleryAttributeId;

        $this->assertEquals([], $errors);
        $this->assertTrue(file_exists(BP . '/pub/media/catalog/product/d/u/duck1.jpg'));
        $this->assertFalse(file_exists(BP . '/pub/media/catalog/product/d/u/duck1_1.jpg'));
        $this->assertFalse(file_exists(BP . '/pub/media/catalog/product/d/u/duck2.png'));

        $media = [
            [$attributeId, '/d/u/duck1.jpg', 'image', '0'],
        ];

        $values = [
            ['0', $product4->id, 'First duck', '1', '0']
        ];

        $this->checkImageData($product4, $media, $values);

        $productS = self::$repository->get("ducky1-product-import", false, 0, true);
        $this->assertEquals('/d/u/duck1.jpg', $productS->getThumbnail());
        $this->assertEquals(null, $productS->getImage());
        $this->assertEquals(null, $productS->getSmallImage());
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

        $results = self::$db->fetchAllNonAssoc("
            SELECT attribute_id, value, media_type, disabled
            FROM {$media}
            WHERE value_id IN (" . implode(',', $valueIds) . ")
            ORDER BY value_id
        ");

        $this->assertEquals($mediaData, $results);

        $results = self::$db->fetchAllNonAssoc("
            SELECT store_id, entity_id, label, position, disabled
            FROM {$value}
            WHERE value_id IN (" . implode(',', $valueIds) . ")
            ORDER BY value_id
        ");

        $this->assertEquals($valueData, $results);
    }

    /**
     * @throws Exception
     */
    public function testGetExistingProduct()
    {
        $errors = [];

        $config = new ImportConfig();

        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $product1 = new VirtualProduct("spooky-action-at-distance");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Spooky");
        $global->setPrice('1.11');

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals([], $errors);

        // update by sku
        $product2 = $importer->getExistingProductBySku("spooky-action-at-distance");
        $importer->importSimpleProduct($product2);
        $importer->flush();

        $this->assertEquals(VirtualProduct::class, get_class($product2));
        $this->assertEquals([], $errors);

        // update by id
        $product3 = $importer->getExistingProductById($product1->id);
        $importer->importSimpleProduct($product3);
        $importer->flush();

        $this->assertEquals(VirtualProduct::class, get_class($product3));
        $this->assertEquals($product1->id, $product3->id);
        $this->assertEquals([], $errors);
    }

    /**
     * @throws Exception
     */
    public function testStockItem()
    {
        $errors = [];

        $config = new ImportConfig();

        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $defaults = [
            'qty' => null,
            'min_qty' => '0.0000',
            'use_config_min_qty' => '1',
            'is_qty_decimal' => '0',
            'backorders' => '0',
            'use_config_backorders' => '1',
            'min_sale_qty' => '1.0000',
            'use_config_min_sale_qty' => '1',
            'max_sale_qty' => '0.0000',
            'use_config_max_sale_qty' => '1',
            'is_in_stock' => '0',
            'low_stock_date' => null,
            'notify_stock_qty' => null,
            'use_config_notify_stock_qty' => '1',
            'manage_stock' => '0',
            'use_config_manage_stock' => '1',
            'stock_status_changed_auto' => '0',
            'use_config_qty_increments' => '1',
            'qty_increments' => '0.0000',
            'use_config_enable_qty_inc' => '1',
            'enable_qty_increments' => '0',
            'is_decimal_divided' => '0'
        ];

        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("snoopy-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Snoopy");
        $global->setPrice('5.95');

        $stock = $product1->defaultStockItem();
        $stock->setQty('11');

        $importer->importSimpleProduct($product1);
        $importer->flush();

        // quantity + default values
        $values = array_merge($defaults, ['qty' => '11.0000']);
        $this->assertEquals([], $errors);
        $this->assertEquals($values, $this->getStockData($product1->id));

        // ------------------------------------------

        $product2 = new SimpleProduct("woodstock-product-import");
        $product2->setAttributeSetByName("Default");
        $global = $product2->global();
        $global->setName("Woodstock");
        $global->setPrice('2.95');

        $stock = $product2->defaultStockItem();
        $stock->setQty('1.5');
        $stock->setMinimumQuantity('1');
        $stock->setUseConfigMinimumQuantity(false);
        $stock->setIsQuantityDecimal(true);
        $stock->setBackorders(ProductStockItem::BACKORDERS_ALLOW_QTY_BELOW_0);
        $stock->setUseConfigBackorders(false);
        $stock->setMinimumSaleQuantity('0.1000');
        $stock->setUseConfigMinimumSaleQuantity(false);
        $stock->setMaximumSaleQuantity(10.5);
        $stock->setUseConfigMaximumSaleQuantity(false);
        $stock->setIsInStock(true);
        $stock->setLowStockDate('2017-12-17');
        $stock->setNotifyStockQuantity('0.2');
        $stock->setUseConfigNotifyStockQuantity(false);
        $stock->setManageStock(true);
        $stock->setUseConfigManageStock(false);
        $stock->setStockStatusChangedAuto(true);
        $stock->setUseConfigQuantityIncrements(false);
        $stock->setQuantityIncrements(0.1);
        $stock->setUseConfigEnableQuantityIncrements(false);
        $stock->setEnableQuantityIncrements(true);
        $stock->setIsDecimalDivided(true);

        $newValues = [
            'qty' => '1.5000',
            'min_qty' => '1.0000',
            'use_config_min_qty' => '0',
            'is_qty_decimal' => '1',
            'backorders' => '1',
            'use_config_backorders' => '0',
            'min_sale_qty' => '0.1000',
            'use_config_min_sale_qty' => '0',
            'max_sale_qty' => '10.5000',
            'use_config_max_sale_qty' => '0',
            'is_in_stock' => '1',
            'low_stock_date' => '2017-12-17 00:00:00',
            'notify_stock_qty' => 0.2,
            'use_config_notify_stock_qty' => '0',
            'manage_stock' => '1',
            'use_config_manage_stock' => '0',
            'stock_status_changed_auto' => '1',
            'use_config_qty_increments' => '0',
            'qty_increments' => '0.1000',
            'use_config_enable_qty_inc' => '0',
            'enable_qty_increments' => '1',
            'is_decimal_divided' => '1'
        ];

        $importer->importSimpleProduct($product2);
        $importer->flush();

        // quantity + default values
        $this->assertEquals([], $errors);
        $this->assertEquals($newValues, $this->getStockData($product2->id));

        // update

        $product3 = new SimpleProduct("woodstock-product-import");
        $product3->setAttributeSetByName("Default");
        $stock = $product3->defaultStockItem();
        $stock->setQty('1.4');

        $importer->importSimpleProduct($product3);
        $importer->flush();

        $newValues = array_merge($newValues, ['qty' => '1.4000']);
        $this->assertEquals([], $errors);
        $this->assertEquals($newValues, $this->getStockData($product3->id));
    }

    protected function getStockData($productId)
    {
        $data = self::$db->fetchRow("
            SELECT
                `qty`,
                `min_qty`,
                `use_config_min_qty`,
                `is_qty_decimal`,
                `backorders`,
                `use_config_backorders`,
                `min_sale_qty`,
                `use_config_min_sale_qty`,
                `max_sale_qty`,
                `use_config_max_sale_qty`,
                `is_in_stock`,
                `low_stock_date`,
                `notify_stock_qty`,
                `use_config_notify_stock_qty`,
                `manage_stock`,
                `use_config_manage_stock`,
                `stock_status_changed_auto`,
                `use_config_qty_increments`,
                `qty_increments`,
                `use_config_enable_qty_inc`,
                `enable_qty_increments`,
                `is_decimal_divided`
            FROM " . self::$metaData->stockItemTable . "
            WHERE product_id = " . $productId . " AND website_id = 0
        ");

        return $data;
    }

    /**
     * @throws Exception
     */
    public function testImportConfigurable()
    {
        $errors = [];

        $config = new ImportConfig();

        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $simple1 = new SimpleProduct('bricks-red-redweiser-product-import');
        $simple1->setAttributeSetByName("Default");
        $global = $simple1->global();
        $global->setName("Bricks Red Redweiser");
        $global->setPrice('99.00');
        $global->setCustomAttribute('color', 1);
        $global->setCustomAttribute('manufacturer', 1);
        $importer->importSimpleProduct($simple1);

        $simple2 = new SimpleProduct('bricks-red-scotts-product-import');
        $simple2->setAttributeSetByName("Default");
        $global = $simple2->global();
        $global->setName("Bricks Red Scotts");
        $global->setPrice('89.00');
        $global->setCustomAttribute('color', 2);
        $global->setCustomAttribute('manufacturer', 1);
        $importer->importSimpleProduct($simple2);

        $simple3 = new SimpleProduct('bricks-orange-scotts-product-import');
        $simple3->setAttributeSetByName("Default");
        $global = $simple3->global();
        $global->setName("Bricks Orange Scotts");
        $global->setPrice('90.00');
        $global->setCustomAttribute('color', 2);
        $global->setCustomAttribute('manufacturer', 2);
        $importer->importSimpleProduct($simple3);

        $configurable = new ConfigurableProduct('scotts-product-import');
        $configurable->setSuperAttributeCodes(['color', 'manufacturer']);
        $configurable->setVariantSkus([
            'bricks-red-redweiser-product-import',
            'bricks-red-scotts-product-import',
            'bricks-orange-scotts-product-import'
        ]);
        $configurable->setAttributeSetByName("Default");
        $global = $configurable->global();
        $global->setName("Bricks");
        $global->setPrice('90.00');

        $importer->importConfigurableProduct($configurable);
        $importer->flush();

        $colorAttributeId = self::$metaData->productEavAttributeInfo['color']->attributeId;
        $manufacturerAttributeId = self::$metaData->productEavAttributeInfo['manufacturer']->attributeId;

        $attributeData = [
            [$configurable->id, $colorAttributeId, '0'],
            [$configurable->id, $manufacturerAttributeId, '1'],
        ];

        $labelData = [
            ['0', '0', 'Color'],
            ['0', '0', 'Manufacturer'],
        ];

        $linkData = [
            [$simple1->id, $configurable->id],
            [$simple2->id, $configurable->id],
            [$simple3->id, $configurable->id],
        ];

        $relationData = [
            [$configurable->id, $simple1->id],
            [$configurable->id, $simple2->id],
            [$configurable->id, $simple3->id],
        ];

        $this->assertEquals([], $errors);
        $this->checkConfigurableData($configurable->id, $attributeData, $labelData, $linkData, $relationData);

        // no change

        $importer->importConfigurableProduct($configurable);
        $importer->flush();

        $this->assertEquals([], $errors);
        $this->checkConfigurableData($configurable->id, $attributeData, $labelData, $linkData, $relationData);

        // import without variants: variants should not be removed

        $configurable = new ConfigurableProduct('scotts-product-import');
        $global->setPrice('91.00');

        $importer->importConfigurableProduct($configurable);
        $importer->flush();

        $this->assertEquals([], $errors);
        $this->checkConfigurableData($configurable->id, $attributeData, $labelData, $linkData, $relationData);

        // change super attribute and simples

        $configurable = new ConfigurableProduct('scotts-product-import');
        $configurable->setSuperAttributeCodes(['color']);
        $configurable->setVariantSkus([
            'bricks-red-redweiser-product-import',
            'bricks-red-scotts-product-import',
        ]);
        $configurable->setAttributeSetByName("Default");
        $global = $configurable->global();
        $global->setName("Bricks");
        $global->setPrice('90.00');

        $importer->importConfigurableProduct($configurable);
        $importer->flush();

        $attributeData = [
            [$configurable->id, $colorAttributeId, '0']
        ];

        $labelData = [
            ['0', '0', 'Color']
        ];

        $linkData = [
            [$simple1->id, $configurable->id],
            [$simple2->id, $configurable->id],
        ];

        $relationData = [
            [$configurable->id, $simple1->id],
            [$configurable->id, $simple2->id],
        ];

        $this->assertEquals([], $errors);
        $this->checkConfigurableData($configurable->id, $attributeData, $labelData, $linkData, $relationData);
    }

    private function checkConfigurableData($configurableId, $attributeData, $labelData, $linkData, $relationData)
    {
        $data = self::$db->fetchAllNonAssoc("
            SELECT product_id, attribute_id, position
            FROM " . self::$metaData->superAttributeTable . "
            WHERE product_id = {$configurableId}
            ORDER BY position
        ");

        $superAttributeIds = self::$db->fetchSingleColumn("
            SELECT product_super_attribute_id
            FROM " . self::$metaData->superAttributeTable . "
            WHERE product_id = {$configurableId}
        ");

        $this->assertEquals($attributeData, $data);

        $data = self::$db->fetchAllNonAssoc("
            SELECT store_id, use_default, value
            FROM " . self::$metaData->superAttributeLabelTable . "
            WHERE product_super_attribute_id IN (" . implode(", ", $superAttributeIds) . ")
        ");

        $this->assertEquals($labelData, $data);

        $data = self::$db->fetchAllNonAssoc("
            SELECT product_id, parent_id
            FROM " . self::$metaData->superLinkTable . "
            WHERE parent_id = {$configurableId}
        ");

        $this->assertEquals($linkData, $data);

        $data = self::$db->fetchAllNonAssoc("
            SELECT parent_id, child_id
            FROM " . self::$metaData->relationTable . "
            WHERE parent_id = {$configurableId}
        ");

        $this->assertSame($relationData, $data);
    }

    /**
     * @throws Exception
     */
    public function testSelectAttributes()
    {
        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("christmas-tree-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Christmas tree");
        $global->setPrice('98.00');
        $global->setSelectAttribute('color', 'white');
        $global->setMultipleSelectAttribute('color_group_product_importer', ['red', 'blue']);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals(["option 'white' not found in attribute 'color'", "option(s) red, blue not found in attribute color_group_product_importer"], $product1->getErrors());

        // auto create option

        $config->autoCreateOptionAttributes = ['color', 'tax_class_id', 'color_group_product_importer'];
        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("christmas-tree-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Christmas tree");
        $global->setPrice('98.00');
        $global->setSelectAttribute('color', 'grey');
        // note: empty value
        $global->setMultipleSelectAttribute('color_group_product_importer', ['red', 'blue', '']);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals([], $product1->getErrors());

        // option value color

        $colorAttributeId = self::$metaData->productEavAttributeInfo['color']->attributeId;

        $colorOptionId = $this->getOptionValue('color', 'grey');

        $value = self::$db->fetchSingleCell("
            SELECT value
            FROM " . self::$metaData->productEntityTable . "_int
            WHERE entity_id = {$product1->id} AND attribute_id = {$colorAttributeId} AND store_id = 0
        ");

        $this->assertEquals($colorOptionId, $value);

        // option values color_group

        $colorGroupAttributeId = self::$metaData->productEavAttributeInfo['color_group_product_importer']->attributeId;

        $colorGroupOptionId1 = $this->getOptionValue('color_group_product_importer', 'red');
        $colorGroupOptionId2 = $this->getOptionValue('color_group_product_importer', 'blue');

        $value = self::$db->fetchSingleCell("
            SELECT value
            FROM " . self::$metaData->productEntityTable . "_varchar
            WHERE entity_id = {$product1->id} AND attribute_id = {$colorGroupAttributeId} AND store_id = 0
        ");

        $this->assertEquals($colorGroupOptionId1 . ',' . $colorGroupOptionId2, $value);

        // set to "", [] => ignore
        $result = $this->getSelectValues($importer, $product1, "", []);
        $this->assertEquals([$colorOptionId, $colorGroupOptionId1 . ',' . $colorGroupOptionId2], $result);

        // set to null, null
        $result = $this->getSelectValues($importer, $product1, null, null);
        $this->assertSame([null, null], $result);

        // reset to 'grey', ['red', 'blue']
        $result = $this->getSelectValues($importer, $product1, "grey", ['red', 'blue']);
        $this->assertEquals([$colorOptionId, $colorGroupOptionId1 . ',' . $colorGroupOptionId2], $result);

        // set config to treat "" as null
        $config->emptyNonTextValueStrategy = ImportConfig::EMPTY_NONTEXTUAL_VALUE_STRATEGY_REMOVE;
        $importer = self::$factory->createImporter($config);

        $result = $this->getSelectValues($importer, $product1, "", []);
        $this->assertSame([null, null], $result);
    }

    /**
     * @param Importer $importer
     * @param SimpleProduct $product
     * @return array
     * @throws Exception
     */
    protected function getSelectValues(Importer $importer, SimpleProduct $product, $color, $colGroup)
    {
        $colorAttributeId = self::$metaData->productEavAttributeInfo['color']->attributeId;
        $colorGroupAttributeId = self::$metaData->productEavAttributeInfo['color_group_product_importer']->attributeId;

        $product->global()->setSelectAttribute('color', $color);
        // note: empty value
        $product->global()->setMultipleSelectAttribute('color_group_product_importer', $colGroup);

        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertEquals([], $product->getErrors());

        $value1 = self::$db->fetchSingleCell("
            SELECT value
            FROM " . self::$metaData->productEntityTable . "_int
            WHERE entity_id = {$product->id} AND attribute_id = {$colorAttributeId} AND store_id = 0
        ");

        $value2 = self::$db->fetchSingleCell("
            SELECT value
            FROM " . self::$metaData->productEntityTable . "_varchar
            WHERE entity_id = {$product->id} AND attribute_id = {$colorGroupAttributeId} AND store_id = 0
        ");

        return [$value1, $value2];
    }

    protected function getOptionValue($attributeCode, $name)
    {
        return self::$db->fetchSingleCell("
            SELECT O.`option_id`
            FROM " . self::$metaData->attributeTable . " A
            INNER JOIN " . self::$metaData->attributeOptionTable . " O ON O.attribute_id = A.attribute_id
            INNER JOIN " . self::$metaData->attributeOptionValueTable . " V ON V.option_id = O.option_id
            WHERE A.`entity_type_id` = ? AND V.store_id = 0
                AND A.attribute_code = ?
                AND V.value = ?
        ", [
            self::$metaData->productEntityTypeId,
            $attributeCode,
            $name
        ]);

    }

    /**
     * @throws Exception
     */
    public function testLinks()
    {
        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("christmas-angel-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Christmas angel");
        $global->setPrice('98.00');

        $product1->setRelatedProductSkus([
            "christmas-baby-jesus-product-import",
            "christmas-josef-product-import"
        ]);

        $product1->setUpSellProductSkus([
            "christmas-josef-product-import",
            "christmas-maria-product-import"
        ]);

        $product1->setCrossSellProductSkus([
            "christmas-baby-jesus-product-import",
            "christmas-maria-product-import"
        ]);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $a = new SimpleProduct("christmas-baby-jesus-product-import");
        $b = new SimpleProduct("christmas-josef-product-import");
        $c = new SimpleProduct("christmas-maria-product-import");

        $importer->importSimpleProduct($a);
        $importer->importSimpleProduct($b);
        $importer->importSimpleProduct($c);
        $importer->flush();

        $links =
            [
                LinkInfo::RELATED => [
                    [$product1->id, $a->id, self::$metaData->linkInfo[LinkInfo::RELATED]->typeId, 1],
                    [$product1->id, $b->id, self::$metaData->linkInfo[LinkInfo::RELATED]->typeId, 2]
                ],
                LinkInfo::UP_SELL => [
                    [$product1->id, $b->id, self::$metaData->linkInfo[LinkInfo::UP_SELL]->typeId, 1],
                    [$product1->id, $c->id, self::$metaData->linkInfo[LinkInfo::UP_SELL]->typeId, 2]
                ],
                LinkInfo::CROSS_SELL => [
                    [$product1->id, $a->id, self::$metaData->linkInfo[LinkInfo::CROSS_SELL]->typeId, 1],
                    [$product1->id, $c->id, self::$metaData->linkInfo[LinkInfo::CROSS_SELL]->typeId, 2]
                ]
            ];

        $this->assertEquals($links, $this->getLinks($product1));

        // do not specify links; they should not be removed

        $product1 = new SimpleProduct("christmas-angel-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Christmas angel");
        $global->setPrice('98.00');

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals($links, $this->getLinks($product1));

        // change the order of the related products. do not specify the other links: they should not be removed

        $product1 = new SimpleProduct("christmas-angel-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Christmas angel");
        $global->setPrice('98.00');

        $product1->setRelatedProductSkus([
            "christmas-josef-product-import",
            "christmas-baby-jesus-product-import"
        ]);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $links[LinkInfo::RELATED] = [
            [$product1->id, $b->id, self::$metaData->linkInfo[LinkInfo::RELATED]->typeId, 1],
            [$product1->id, $a->id, self::$metaData->linkInfo[LinkInfo::RELATED]->typeId, 2]
        ];

        $this->assertEquals($links, $this->getLinks($product1));

        // remove links: they should be removed

        $product1 = new SimpleProduct("christmas-angel-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Christmas angel");
        $global->setPrice('98.00');

        $product1->setRelatedProductSkus([]);
        $product1->setUpSellProductSkus([]);
        $product1->setCrossSellProductSkus([]);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $links =
            [
                LinkInfo::RELATED => [
                ],
                LinkInfo::UP_SELL => [
                ],
                LinkInfo::CROSS_SELL => [
                ]
            ];

        $this->assertEquals($links, $this->getLinks($product1));
    }

    private function getLinks($product)
    {
        $result = [];

        foreach ([LinkInfo::RELATED, LinkInfo::UP_SELL, LinkInfo::CROSS_SELL] as $linkType) {

            $linkInfo = self::$metaData->linkInfo[$linkType];

            $r = self::$db->fetchAllNonAssoc("
                SELECT L.product_id, L.linked_product_id, L.link_type_id, P.value
                FROM " . self::$metaData->linkTable . " L
                INNER JOIN " . self::$metaData->linkAttributeIntTable . " P ON P.link_id = L.link_id AND P.product_link_attribute_id = {$linkInfo->positionAttributeId}
                WHERE product_id = {$product->id}
                ORDER BY P.value
            ");

            $result[$linkType] = $r;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function testGroupedProduct()
    {
        $errors = [];

        $config = new ImportConfig();

        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $simple1 = new SimpleProduct("knife-product-import");
        $simple1->setAttributeSetByName("Default");
        $global = $simple1->global();
        $global->setName("Knife");
        $global->setPrice('2.25');

        $simple2 = new SimpleProduct("fork-product-import");
        $simple2->setAttributeSetByName("Default");
        $global = $simple2->global();
        $global->setName("Fork");
        $global->setPrice('2.25');

        $group = new GroupedProduct("cutlery-product-import");
        $group->setMembers([
            new GroupedProductMember("knife-product-import", 2),
            new GroupedProductMember("fork-product-import", 3),
            // this one does not exist yet
            new GroupedProductMember("spoon-product-import", 4),
        ]);
        $group->setAttributeSetByName("Default");

        $global = $group->global();
        $global->setName("Cutlery");
        $global->setPrice('25.00');
        $global->setGiftMessageAvailable(true);

        $importer->importSimpleProduct($simple1);
        $importer->importSimpleProduct($simple2);
        $importer->importGroupedProduct($group);
        $importer->flush();

        $this->assertSame([], $errors);

        // look up id for simple3 that must have been created as a placeholder
        $simple3 = new SimpleProduct("spoon-product-import");
        $simple3->setAttributeSetByName("Default");

        $importer->importSimpleProduct($simple3);
        $importer->flush();

        $this->assertSame([], $errors);

        $memberData = [
            [$group->id, $simple1->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 1, 2.0],
            [$group->id, $simple2->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 2, 3.0],
            [$group->id, $simple3->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 3, 4.0],
        ];

        $this->assertEquals($memberData, $this->getMemberData($group));

        // change the order of the member products and the default quantities, remove 1, add 1

        $group = new GroupedProduct("cutlery-product-import");
        $group->setMembers([
            new GroupedProductMember("teaspoon-product-import", 3),
            new GroupedProductMember("knife-product-import", 2.5),
            new GroupedProductMember("spoon-product-import", 4),
        ]);
        $group->setAttributeSetByName("Default");

        $importer->importGroupedProduct($group);
        $importer->flush();

        $this->assertSame([], $errors);

        // look up id for simple4 that must have been created as a placeholder
        $simple4 = new SimpleProduct("teaspoon-product-import");
        $simple4->setAttributeSetByName("Default");
        $importer->importSimpleProduct($simple4);
        $importer->flush();

        $this->assertSame([], $errors);

        $memberData = [
            [$group->id, $simple4->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 1, 3.0],
            [$group->id, $simple1->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 2, 2.5],
            [$group->id, $simple3->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 3, 4.0],
        ];

        $this->assertEquals($memberData, $this->getMemberData($group));

        // member not set: they should not be removed

        $group = new GroupedProduct("cutlery-product-import");

        $importer->importGroupedProduct($group);
        $importer->flush();

        $memberData = [
            [$group->id, $simple4->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 1, 3.0],
            [$group->id, $simple1->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 2, 2.5],
            [$group->id, $simple3->id, self::$metaData->linkInfo[LinkInfo::SUPER]->typeId, 3, 4.0],
        ];

        $this->assertEquals($memberData, $this->getMemberData($group));

        // no members: they should be removed

        $group = new GroupedProduct("cutlery-product-import");
        $group->setMembers([]);

        $importer->importGroupedProduct($group);
        $importer->flush();

        $memberData =
            [
            ];

        $this->assertEquals($memberData, $this->getMemberData($group));

        // one member is invalid

        $simple1 = new SimpleProduct("leaf-product-import");
        $simple1->setAttributeSetByName("Default");
        $global = $simple1->global();
        $global->setName("Knife");
        $global->setPrice('2.25');

        $simple2 = new SimpleProduct("stem-product-import");
        $simple2->setAttributeSetByName("Default");
        // attributes missing

        $group = new GroupedProduct("tree-product-import");
        $group->setMembers([
            new GroupedProductMember("leaf-product-import", 2),
            new GroupedProductMember("stem-product-import", 3),
        ]);
        $group->setAttributeSetByName("Default");

        $global = $group->global();
        $global->setName("Tree");
        $global->setPrice('25.00');

        $importer->importSimpleProduct($simple1);
        $importer->importSimpleProduct($simple2);
        $importer->importGroupedProduct($group);
        $importer->flush();

        $this->assertSame([
            'missing name',
            'missing price',
            'A member product is invalid: stem-product-import'
        ], $errors);
    }

    private function getMemberData($group)
    {
        $linkInfo = self::$metaData->linkInfo[LinkInfo::SUPER];

        $r = self::$db->fetchAllNonAssoc("
            SELECT L.product_id, L.linked_product_id, L.link_type_id, P.value, Q.value
            FROM " . self::$metaData->linkTable . " L
            INNER JOIN " . self::$metaData->linkAttributeIntTable . " P ON P.link_id = L.link_id AND P.product_link_attribute_id = {$linkInfo->positionAttributeId}
            INNER JOIN " . self::$metaData->linkAttributeDecimalTable . " Q ON Q.link_id = L.link_id AND Q.product_link_attribute_id = {$linkInfo->defaultQuantityAttributeId}
            WHERE product_id = {$group->id}
            ORDER BY P.value
        ");

        return $r;
    }

    /**
     * @throws Exception
     */
    public function testTierPrices()
    {
        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("window-sill-modern-product-import");
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Window sill modern");
        $global->setPrice('225.00');

        $product1->setTierPrices([
            new TierPrice(10, '12.25', 'NOT LOGGED IN', 'base'),
            new TierPrice(20, '12.15'),
        ]);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $expected = [
            [$product1->id, 0, 0, 10, 12.25, 1, 0],
            [$product1->id, 1, 0, 20, 12.15, 0, 0],
        ];

        $this->assertEquals([], $product1->getErrors());
        $this->assertEquals($expected, $this->getTierPrices($product1->id));

        // no tier prices specified: do not remove tier prices

        $product1 = new SimpleProduct("window-sill-modern-product-import");
        $product1->setAttributeSetByName("Default");

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals([], $product1->getErrors());
        $this->assertEquals($expected, $this->getTierPrices($product1->id));

        // update: change one entry (causes an insert and a delete) and update a price value (update)

        $product1 = new SimpleProduct("window-sill-modern-product-import");
        $product1->setAttributeSetByName("Default");

        $product1->setTierPrices([
            new TierPrice(10, '12.25', 'General', 'base', 12.30),
            new TierPrice(20, '12.10'),
        ]);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $expected = [
            [$product1->id, 0, 1, 10, 12.25, 1, 12.30],
            [$product1->id, 1, 0, 20, 12.10, 0, 0],
        ];

        $this->assertEquals([], $product1->getErrors());
        $this->assertEquals($expected, $this->getTierPrices($product1->id));
    }

    /**
     * @param $productId
     * @return array
     */
    public function getTierPrices($productId)
    {
        return self::$db->fetchAllNonAssoc("
            SELECT entity_id, all_groups, customer_group_id, qty, value, website_id, percentage_value
            FROM " . self::$metaData->tierPriceTable . "
            WHERE entity_id = {$productId}
            ORDER BY qty
        ");
    }

    /**
     * @throws Exception
     */
    public function testVirtualProduct()
    {
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $product = new VirtualProduct('virtual_product_import');
        $product->setAttributeSetByName("Default");

        $global = $product->global();
        $global->setName("Washing the dishes");
        $global->setPrice('2.50');

        $importer->importVirtualProduct($product);
        $importer->flush();

        $this->assertEquals([], $errors);
        $this->assertNotNull($product->id);
    }

    /**
     * @throws Exception
     */
    public function testDownloadableProduct()
    {
        @unlink(BP . '/pub/media/downloadable/files/links/d/u/duck1.jpg');
        @unlink(BP . '/pub/media/downloadable/files/links/d/u/duck1_1.jpg');
        @unlink(BP . '/pub/media/downloadable/files/link_samples/d/u/duck2.png');
        @unlink(BP . '/pub/media/downloadable/files/samples/d/u/duck3.png');

        $errors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $downloadable = new DownloadableProduct("morlord-the-game");
        $downloadable->setAttributeSetByName("Default");

        $downloadable->global()->setName("Morlord the game");
        $downloadable->global()->setPrice("25.95");
        $downloadable->global()->setLinksPurchasedSeparately(true);
        $downloadable->global()->setLinksTitle("Links");
        $downloadable->global()->setSamplesTitle("Samples");

        $downloadable->setDownloadLinks([
            $link1 = new DownloadLink('http://download-resources.net/morlord-setup.exe', 0, true),
            $link2 = new DownloadLink(__DIR__ . '/../images/duck1.jpg', 10, false, __DIR__ . '/../images/duck2.png')
        ]);

        $downloadable->global()->setDownloadLinkInformation($link1, "Morlord The Game", "12.95");
        $downloadable->storeView('default')->setDownloadLinkInformation($link1, "Morlord Het Spel", "13.45");

        $downloadable->global()->setDownloadLinkInformation($link2, "Morlord The Game 2", "22.95");
        $downloadable->storeView('default')->setDownloadLinkInformation($link2, "Morlord Het Spel 2", "23.45");

        $downloadable->setDownloadSamples([
            $sample1 = new DownloadSample(__DIR__ . '/../images/duck3.png'),
            $sample2 = new DownloadSample('https://download-resources.net/morlord-sample.pdf')
        ]);

        $downloadable->global()->setDownloadSampleInformation($sample1, "Morlord The Game - Example");
        $downloadable->storeView('default')->setDownloadSampleInformation($sample1, "Morlord Het Spel - Voorbeeld");

        $downloadable->global()->setDownloadSampleInformation($sample2, "Morlord The Game - Example 2");
        $downloadable->storeView('default')->setDownloadSampleInformation($sample2, "Morlord Het Spel - Voorbeeld 2");

        $importer->importDownloadableProduct($downloadable);
        $importer->flush();

        $this->assertEquals([], $errors);

        $this->checkDownloadable($downloadable);

        // another import should give the same results

        $importer->importDownloadableProduct($downloadable);
        $importer->flush();

        $this->assertEquals([], $errors);

        $this->checkDownloadable($downloadable);
    }

    private function checkDownloadable($downloadable)
    {

        $linkResults = self::$db->fetchAllNonAssoc("
            SELECT sort_order, number_of_downloads, is_shareable, link_url, link_file, link_type, sample_url, sample_file, sample_type
            FROM " . self::$metaData->downloadableLinkTable . "
            WHERE product_id = {$downloadable->id}
        ");

        $this->assertEquals([
            ['1', '0', '1', 'http://download-resources.net/morlord-setup.exe', null, 'url', null, null, null],
            ['2', '10', '0', null, '/d/u/duck1.jpg', 'file', null, '/d/u/duck2.png', 'file']
        ], $linkResults);

        $sampleResults = self::$db->fetchAllNonAssoc("
            SELECT sort_order, sample_url, sample_file, sample_type
            FROM " . self::$metaData->downloadableSampleTable . "
            WHERE product_id = {$downloadable->id}
        ");

        $this->assertEquals([
            ['1', null, '/d/u/duck3.png', 'file'],
            ['2', 'https://download-resources.net/morlord-sample.pdf', null, 'url']
        ], $sampleResults);

        $linkIds = self::$db->fetchSingleColumn("SELECT link_id FROM " . self::$metaData->downloadableLinkTable . " WHERE product_id = {$downloadable->id}");

        $linkPriceResults = self::$db->fetchAllNonAssoc("
            SELECT website_id, price
            FROM " . self::$metaData->downloadableLinkPriceTable . "
            WHERE link_id IN (" . implode(',', $linkIds) . ")
            ORDER BY price_id
        ");

        $this->assertEquals([
            ['0', 12.95],
            ['0', 22.95],
            ['1', 13.45],
            ['1', 23.45]
        ], $linkPriceResults);

        $linkTitleResults = self::$db->fetchAllNonAssoc("
            SELECT store_id, title
            FROM " . self::$metaData->downloadableLinkTitleTable . "
            WHERE link_id IN (" . implode(',', $linkIds) . ")
            ORDER BY title_id
        ");

        $this->assertEquals([
            ['0', "Morlord The Game"],
            ['0', "Morlord The Game 2"],
            ['1', "Morlord Het Spel"],
            ['1', "Morlord Het Spel 2"]
        ], $linkTitleResults);

        $sampleIds = self::$db->fetchSingleColumn("SELECT sample_id FROM " . self::$metaData->downloadableSampleTable . " WHERE product_id = {$downloadable->id}");

        $sampleTitleResults = self::$db->fetchAllNonAssoc("
            SELECT store_id, title
            FROM " . self::$metaData->downloadableSampleTitleTable . "
            WHERE sample_id IN (" . implode(',', $sampleIds) . ")
            ORDER BY title_id
        ");

        $this->assertEquals([
            ['0', "Morlord The Game - Example"],
            ['0', "Morlord The Game - Example 2"],
            ['1', "Morlord Het Spel - Voorbeeld"],
            ['1', "Morlord Het Spel - Voorbeeld 2"]
        ], $sampleTitleResults);

        $this->assertTrue(file_exists(BP . "/pub/media/downloadable/files/links/d/u/duck1.jpg"));
        $this->assertTrue(file_exists(BP . "/pub/media/downloadable/files/link_samples/d/u/duck2.png"));
        $this->assertTrue(file_exists(BP . "/pub/media/downloadable/files/samples/d/u/duck3.png"));

        $this->assertTrue(!file_exists(BP . "/pub/media/downloadable/files/links/d/u/duck1_1.jpg"));

        @unlink(BP . '/pub/media/downloadable/files/links/d/u/duck1.jpg');
        @unlink(BP . '/pub/media/downloadable/files/link_samples/d/u/duck2.png');
        @unlink(BP . '/pub/media/downloadable/files/samples/d/u/duck3.png');
    }

    /**
     * @throws Exception
     */
    public function testBundleProduct()
    {
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $bundle = new BundleProduct("ibm-pc-product-import");
        $bundle->setAttributeSetByName("Default");

        $global = $bundle->global();
        $global->setName("IBM PC");
        $global->setPrice("25.95");
        $global->setPriceType(BundleProductStoreView::PRICE_TYPE_DYNAMIC);
        $global->setSkuType(BundleProductStoreView::SKU_TYPE_DYNAMIC);
        $global->setWeightType(BundleProductStoreView::WEIGHT_TYPE_DYNAMIC);
        $global->setPriceView(BundleProductStoreView::PRICE_VIEW_PRICE_RANGE);
        $global->setShipmentType(BundleProductStoreView::SHIPMENT_TYPE_TOGETHER);

        $bundle->setOptions([
            $option1 = new BundleProductOption(BundleProduct::INPUT_TYPE_DROP_DOWN, true),
            $option2 = new BundleProductOption(BundleProduct::INPUT_TYPE_MULTIPLE_SELECT, false)
        ]);

        $option1->setProductSelections([
            new BundleProductSelection('monitor1-product-import', true, 1, '25.00', '1', false),
            new BundleProductSelection('monitor2-product-import', false, 0, '300.00', '2', true)
        ]);

        $global->setOptionTitle($option1, 'Monitor');
        $bundle->storeView('default')->setOptionTitle($option1, 'Monitor A');

        $option2->setProductSelections([
            new BundleProductSelection('keyboard-product-import', false, 2, '200.00', '2', true)
        ]);

        $global->setOptionTitle($option2, 'Keyboard');
        $bundle->storeView('default')->setOptionTitle($option2, 'Keyboard A');

        $importer->importBundleProduct($bundle);
        $importer->flush();

        $this->assertEquals([], $errors);

        $firstOptionId = $this->checkBundleProduct($bundle);

        // update, no change

        $importer->importBundleProduct($bundle);
        $importer->flush();

        $this->assertEquals([], $errors);


        // update with change

        $bundle->setOptions([
            $option1,
            $option2,
            new BundleProductOption(BundleProduct::INPUT_TYPE_RADIO_BUTTONS, false)
        ]);

        $importer->importBundleProduct($bundle);
        $importer->flush();

        $this->assertEquals([], $errors);

        $newFirstOptionId = $this->checkBundleProduct($bundle, true);

        $this->assertNotEquals($firstOptionId, $newFirstOptionId);
    }

    protected function checkBundleProduct(BundleProduct $bundle, bool $extended = false)
    {
        $optionResults = self::$db->fetchAllNonAssoc("
            SELECT required, position, type
            FROM " . self::$metaData->bundleOptionTable . "
            WHERE parent_id = {$bundle->id}
            ORDER BY position
        ");

        if (!$extended) {
            $this->assertEquals([
                ['1', '1', 'select'],
                ['0', '2', 'multi']
            ], $optionResults);
        } else {
            $this->assertEquals([
                ['1', '1', 'select'],
                ['0', '2', 'multi'],
                ['0', '3', 'radio']
            ], $optionResults);
        }

        $optionIds = self::$db->fetchSingleColumn("
            SELECT option_id
            FROM " . self::$metaData->bundleOptionTable . "
            WHERE parent_id = {$bundle->id}
        ");

        $selectionResults = self::$db->fetchAllNonAssoc("
            SELECT `product_id`, `is_default`, `selection_price_type`, `selection_price_value`, `selection_qty`, `selection_can_change_qty`
            FROM " . self::$metaData->bundleSelectionTable . "
            WHERE option_id IN (" . implode(', ', $optionIds) . ")
            ORDER BY selection_id
        ");

        $p1 = $bundle->getOptions()[0]->getSelections()[0]->getProductId();
        $p2 = $bundle->getOptions()[0]->getSelections()[1]->getProductId();
        $p3 = $bundle->getOptions()[1]->getSelections()[0]->getProductId();

        $this->assertEquals([
            [$p1, 1, 1, '25.0000', 1, 0],
            [$p2, 0, 0, '300.0000', 2, 1],
            [$p3, 0, 2, '200.0000', 2, 1],
        ], $selectionResults);

        $titleResults = self::$db->fetchAllNonAssoc("
            SELECT store_id, title, parent_product_id
            FROM " . self::$metaData->bundleOptionValueTable . "
            WHERE option_id IN (" . implode(', ', $optionIds) . ")
            ORDER BY value_id
        ");

        $this->assertEquals([
            [0, 'Monitor', $bundle->id],
            [0, 'Keyboard', $bundle->id],
            [1, 'Monitor A', $bundle->id],
            [1, 'Keyboard A', $bundle->id],
        ], $titleResults);

        return min($optionIds);
    }

    /**
     * @throws Exception
     */
    public function testCustomOptions()
    {
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $product = new SimpleProduct('fine-pen-product-import');
        $product->setAttributeSetByName("Default");

        $global = $product->global();
        $global->setName("Fine pen");
        $global->setPrice('14.95');

        $product->setCustomOptions([
            $inscription = CustomOption::createCustomOptionTextField('inscription', true, 21),
            $note = CustomOption::createCustomOptionTextArea('note', true, 255),
            $idCard = CustomOption::createCustomOptionFile("id-card", false, "jpg jpeg", 5000, 7000),
            $date = CustomOption::createCustomOptionDate("", true),
            $dateTime = CustomOption::createCustomOptionDateTime(null, true),
            $time = CustomOption::createCustomOptionTime("time", true),
            $colors = CustomOption::createCustomOptionDropDown(true, ["red", "green", "blue"]),
            $frames = CustomOption::createCustomOptionRadioButtons(true, [null, ""]),
            $extras = CustomOption::createCustomOptionCheckboxGroup(true, ["mayonaise", "ketchup", "mosterd"]),
            $toppings = CustomOption::createCustomOptionMultipleSelect(true, ["nuts", "syrup", "m-and-ms"])
        ]);

        $product->global()->setCustomOptionTitle($inscription, "Inscription");
        $product->global()->setCustomOptionPrice($inscription, "0.50", ProductStoreView::PRICE_TYPE_FIXED);

        $product->global()->setCustomOptionTitle($note, "Note");
        $product->global()->setCustomOptionPrice($note, "0.10", ProductStoreView::PRICE_TYPE_FIXED);

        $product->global()->setCustomOptionTitle($idCard, "Id card");
        $product->global()->setCustomOptionPrice($idCard, "0", ProductStoreView::PRICE_TYPE_FIXED);

        $product->global()->setCustomOptionTitle($date, "Date");
        $product->global()->setCustomOptionPrice($date, "10", ProductStoreView::PRICE_TYPE_PERCENT);

        $product->global()->setCustomOptionTitle($dateTime, "Date and time");
        $product->global()->setCustomOptionPrice($dateTime, "20", ProductStoreView::PRICE_TYPE_PERCENT);

        $product->global()->setCustomOptionTitle($time, "Time");
        $product->global()->setCustomOptionPrice($time, "30", ProductStoreView::PRICE_TYPE_PERCENT);

        $product->global()->setCustomOptionTitle($colors, "Color");
        $product->global()->setCustomOptionValues($colors, [
            new CustomOptionValue("0.10", ProductStoreView::PRICE_TYPE_FIXED, 'Red'),
            new CustomOptionValue("0.15", ProductStoreView::PRICE_TYPE_FIXED, 'Green'),
            new CustomOptionValue("0.25", ProductStoreView::PRICE_TYPE_FIXED, 'Blue')
        ]);

        $product->global()->setCustomOptionTitle($frames, "Frame");
        $product->global()->setCustomOptionValues($frames, [
            new CustomOptionValue("10", ProductStoreView::PRICE_TYPE_PERCENT, 'Wood'),
            new CustomOptionValue("15", ProductStoreView::PRICE_TYPE_PERCENT, 'Iron')
        ]);

        $product->global()->setCustomOptionTitle($extras, "Extras");
        $product->global()->setCustomOptionValues($extras, [
            new CustomOptionValue("0.05", ProductStoreView::PRICE_TYPE_FIXED, 'Mayonaise'),
            new CustomOptionValue("0.05", ProductStoreView::PRICE_TYPE_FIXED, 'Ketchup'),
            new CustomOptionValue("0.10", ProductStoreView::PRICE_TYPE_FIXED, 'Mosterd')
        ]);

        $product->global()->setCustomOptionTitle($toppings, "Toppings");
        $product->global()->setCustomOptionValues($toppings, [
            new CustomOptionValue("0.10", ProductStoreView::PRICE_TYPE_FIXED, 'Nuts'),
            new CustomOptionValue("0.10", ProductStoreView::PRICE_TYPE_FIXED, 'Syrup'),
            new CustomOptionValue("0.10", ProductStoreView::PRICE_TYPE_FIXED, "M & M's")
        ]);

        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertEquals([], $errors);

        $actual = self::$db->fetchAllNonAssoc("
            SELECT type, is_require, sku, max_characters, file_extension, image_size_x, image_size_y, sort_order, T.store_id, T.title, P.store_id, P.price, P.price_type
            FROM catalog_product_option O
            LEFT JOIN catalog_product_option_title T ON T.option_id = O.option_id
            LEFT JOIN catalog_product_option_price P ON P.option_id = O.option_id
            WHERE product_id = {$product->id}
            ORDER BY sort_order
        ");

        $expected = [
            ['field', '1', 'inscription', '21', null, '0', '0', '1', '0', 'Inscription', '0', 0.5, 'fixed'],
            ['area', '1', 'note', 255, null, '0', '0', '2', '0', 'Note', '0', 0.1, 'fixed'],
            ['file', '0', 'id-card', '0', 'jpg jpeg', '5000', '7000', '3', '0', 'Id card', '0', 0., 'fixed'],
            ['date', '1', null, 0, null, '0', '0', '4', '0', 'Date', '0', 10.0, 'percent'],
            ['date_time', '1', null, '0', null, '0', '0', '5', '0', 'Date and time', '0', 20.0, 'percent'],
            ['time', '1', 'time', '0', null, '0', '0', '6', '0', 'Time', '0', 30.0, 'percent'],
            ['drop_down', '1', null, '0', null, '0', '0', '7', '0', 'Color', null, null, null],
            ['radio', '1', null, '0', null, '0', '0', '8', '0', 'Frame', null, null, null],
            ['checkbox', '1', null, '0', null, '0', '0', '9', '0', 'Extras', null, null, null],
            ['multiple', '1', null, '0', null, '0', '0', '10', '0', 'Toppings', null, null, null]
        ];

        $this->assertEquals($expected, $actual);

        $optionIds = self::$db->fetchSingleColumn("
            SELECT option_id FROM catalog_product_option
            WHERE product_id = {$product->id}
        ");

        $actual = self::$db->fetchAllNonAssoc("
            SELECT V.sku, V.sort_order, T.store_id, T.title, P.store_id, P.price, P.price_type
            FROM catalog_product_option_type_value V
            LEFT JOIN catalog_product_option_type_title T ON T.option_type_id = V.option_type_id
            LEFT JOIN catalog_product_option_type_price P ON P.option_type_id = V.option_type_id
            WHERE V.option_id IN (" . implode(',', $optionIds) . ")
            ORDER BY V.option_id, V.sort_order
        ");

        $expected = [
            ["red", "1", "0", "Red", "0", 0.1, "fixed"],
            ["green", "2", "0", "Green", "0", 0.15, "fixed"],
            ["blue", "3", "0", "Blue", "0", 0.25, "fixed"],
            [null, "1", "0", "Wood", "0", 10.0, "percent"],
            [null, "2", "0", "Iron", "0", 15.0, "percent"],
            ["mayonaise", "1", "0", "Mayonaise", "0", 0.05, "fixed"],
            ["ketchup", "2", "0", "Ketchup", "0", 0.05, "fixed"],
            ["mosterd", "3", "0", "Mosterd", "0", 0.1, "fixed"],
            ["nuts", "1", "0", "Nuts", "0", 0.1, "fixed"],
            ["syrup", "2", "0", "Syrup", "0", 0.1, "fixed"],
            ["m-and-ms", "3", "0", "M & M's", "0", 0.1, "fixed"],
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * @throws Exception
     */
    public function testChangeProductType()
    {
        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product1 = new VirtualProduct('type-transformer-product-import');
        $product1->setAttributeSetByName("Default");

        $global = $product1->global();
        $global->setName("Type Transformer");
        $global->setPrice('6.75');

        $importer->importVirtualProduct($product1);
        $importer->flush();

        $product2 = new BundleProduct('type-transformer-product-import');

        $importer->importBundleProduct($product2);
        $importer->flush();

        // default: virtual to bundle is ok

        $this->assertEquals([], $product2->getErrors());

        $type = self::$db->fetchSingleCell("
            SELECT `type_id`
            FROM " . self::$metaData->productEntityTable . "
            WHERE entity_id = " . $product1->id . "
        ");

        $this->assertSame("bundle", $type);

        $product3 = new SimpleProduct('type-transformer-product-import');

        $importer->importSimpleProduct($product3);
        $importer->flush();

        // default: bundle to simple is not ok

        $this->assertSame(['Type conversion losing data from bundle to simple is not allowed'], $product3->getErrors());

        // forbidden: no conversion is ok

        $config->productTypeChange = ImportConfig::PRODUCT_TYPE_CHANGE_FORBIDDEN;
        $importer = self::$factory->createImporter($config);
        $product4 = new SimpleProduct('type-transformer-product-import');
        $importer->importSimpleProduct($product4);
        $importer->flush();

        $this->assertEquals(['Type conversion is not allowed'], $product4->getErrors());

        // allowed: bundle to simple is ok

        $config->productTypeChange = ImportConfig::PRODUCT_TYPE_CHANGE_ALLOWED;
        $importer = self::$factory->createImporter($config);
        $product5 = new SimpleProduct('type-transformer-product-import');
        $importer->importSimpleProduct($product5);
        $importer->flush();

        $this->assertEquals([], $product5->getErrors());
    }

    /**
     * @throws Exception
     */
    public function testEmptyValues()
    {
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $product1 = new SimpleProduct('disposable-product-import');
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Disposable");
        $global->setPrice('0');

        // default values
        $this->checkEmptyValues($config, $product1,
            [" disposable ", " Disposable ", " 2.10 "],
            ["disposable", " Disposable ", "2.1000"]
        );

        // "" default: ignore
        $this->checkEmptyValues($config, $product1,
            ["", "", ""],
            ["disposable", " Disposable ", "2.1000"]
        );

        // null => remove
        $this->checkEmptyValues($config, $product1,
            [null, null, null],
            [null, null, null]
        );

        // "" => remove
        $config->emptyNonTextValueStrategy = ImportConfig::EMPTY_TEXTUAL_VALUE_STRATEGY_REMOVE;
        $config->emptyTextValueStrategy = ImportConfig::EMPTY_TEXTUAL_VALUE_STRATEGY_REMOVE;

        $this->checkEmptyValues($config, $product1,
            ["", "", ""],
            [null, null, null]
        );
    }

    /**
     * @throws Exception
     */
    public function testEmptyValuesStoreView()
    {
        $attributeId = self::$metaData->productEavAttributeInfo['name']->attributeId;
        $type = self::$metaData->productEavAttributeInfo['name']->backendType;

        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct('test-product1');
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Test Product 1");
        $global->setPrice('0');
        $default = $product1->storeView('default');
        $default->setName("Test Product 1 Default");

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertSame([], $product1->getErrors());

        $value = self::$db->fetchSingleCell("
                SELECT `value`
                FROM `" . self::$metaData->productEntityTable . "_" . $type . "`
                WHERE entity_id = ? AND attribute_id = ? AND store_id = ?
            ", [
            $product1->id,
            $attributeId,
            self::$metaData->storeViewMap['default']
        ]);

        $this->assertSame("Test Product 1 Default", $value);

        // set name to null

        $product1 = new SimpleProduct('test-product1');
        $product1->setAttributeSetByName("Default");
        $global = $product1->global();
        $global->setName("Test Product 1");
        $global->setPrice('0');
        $default = $product1->storeView('default');
        $default->setName(NULL);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertSame([], $product1->getErrors());

        $value = self::$db->fetchSingleCell("
                SELECT `value`
                FROM `" . self::$metaData->productEntityTable . "_" . $type . "`
                WHERE entity_id = ? AND attribute_id = ? AND store_id = ?
            ", [
            $product1->id,
            $attributeId,
            self::$metaData->storeViewMap['default']
        ]);

        $this->assertSame(null, $value);
    }

    /**
     * @param ImportConfig $config
     * @param SimpleProduct $product
     * @param array $set
     * @param array $expectedGlobal
     * @throws Exception
     */
    public function checkEmptyValues(ImportConfig $config, SimpleProduct $product, array $set, array $expectedGlobal)
    {
        $importer = self::$factory->createImporter($config);

        // trimmed varchar
        $product->global()->setMetaTitle($set[0]);

        // non-trimmed text
        $product->global()->setDescription($set[1]);

        // non-textual (price)
        $product->global()->setMsrp($set[2]);

        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertSame([], $product->getErrors());

        foreach (['meta_title', 'description', 'msrp'] as $i => $attributeCode) {

            $attributeId = self::$metaData->productEavAttributeInfo[$attributeCode]->attributeId;
            $type = self::$metaData->productEavAttributeInfo[$attributeCode]->backendType;

            $value = self::$db->fetchSingleCell("
                SELECT `value`
                FROM `" . self::$metaData->productEntityTable . "_" . $type . "`
                WHERE entity_id = ? AND attribute_id = ? AND store_id = ?
            ", [
                $product->id,
                $attributeId,
                0
            ]);

            if ($i == 2) {
                $this->assertSame((float)$expectedGlobal[$i], (float)$value);
            } else {
                $this->assertSame($expectedGlobal[$i], $value);
            }
        }
    }
}
