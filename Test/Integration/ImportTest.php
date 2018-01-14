<?php

namespace BigBridge\ProductImport\Test\Integration;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use BigBridge\ProductImport\Api\ConfigurableProduct;
use BigBridge\ProductImport\Api\ProductStoreView;
use BigBridge\ProductImport\Api\TierPrice;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\Resolver\CategoryImporter;
use BigBridge\ProductImport\Api\SimpleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\Product;

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

        self::$metaData->productEavAttributeInfo['color_group_product_importer'] = new EavAttributeInfo('color_group_product_importer', $insertId, false, 'varchar', 'catalog_product_entity_varchar', 'multiselect', [], 1);
    }

    public static function tearDownAfterClass()
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
        $this->assertEquals(4, $product1->getAttributeSetId());
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

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
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

            $this->assertEquals($attributeSetId, $productS->getAttributeSetId());
            $this->assertEquals($taxClassId, $productS->getTaxClassId());
            $this->assertEquals($visibility, $productS->getVisibility());
            $this->assertEquals($status, $productS->getStatus());

        } catch (NoSuchEntityException $e) {
            $this->assertTrue(false);
        }
    }

    /**
     * @throws Exception
     * @throws NoSuchEntityException
     */
    public function testImages()
    {
        @unlink(BP . '/pub/media/catalog/product/d/u/duck1.jpg');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck2.png');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck3.png');
        @unlink(BP . '/pub/media/catalog/product/d/u/duck3_1.png');

        $errors = [];

        $config = new ImportConfig();

        $config->resultCallbacks[] = function(Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        // use 2 images with 2 roles

        $product1 = new SimpleProduct("ducky1-product-import");
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
        $this->assertEquals('/d/u/duck2.png', $productS->getImage());
        $this->assertEquals('/d/u/duck3_1.png', $productS->getSmallImage());

        // update, no changes (important be we now have ducky3_1.png, it should stay that way)

        $importer->importSimpleProduct($product2);
        $importer->flush();

        $this->checkImageData($product2, $media, $values);
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

    /**
     * @throws Exception
     */
    public function testStockItem()
    {
        $errors = [];

        $config = new ImportConfig();

        $config->resultCallbacks[] = function (Product $product) use (&$errors) {
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
        $global = $product1->global();
        $global->setName("Snoopy");
        $global->setPrice('5.95');

        $stock = $product1->defaultStockItem();
        $stock->setQuantity('11');

        $importer->importSimpleProduct($product1);
        $importer->flush();

        // quantity + default values
        $values = array_merge($defaults, ['qty' => '11.0000']);
        $this->assertEquals([], $errors);
        $this->assertEquals($values, $this->getStockData($product1->id));

        // ------------------------------------------

        $product2 = new SimpleProduct("woodstock-product-import");
        $global = $product2->global();
        $global->setName("Woodstock");
        $global->setPrice('2.95');

        $stock = $product2->defaultStockItem();
        $stock->setQuantity('1.5');
        $stock->setMinimumQuantity('1');
        $stock->setUseConfigMinimumQuantity(false);
        $stock->setIsQuantityDecimal(true);
        $stock->setBackorders(true);
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
            'notify_stock_qty' => '0.200',
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
        $stock = $product3->defaultStockItem();
        $stock->setQuantity('1.4');

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

        $config->resultCallbacks[] = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        $simple1 = new SimpleProduct('bricks-red-redweiser-product-import');
        $global = $simple1->global();
        $global->setName("Bricks Red Redweiser");
        $global->setPrice('99.00');
        $global->setCustomAttribute('color', 1);
        $global->setCustomAttribute('manufacturer', 1);

        $simple2 = new SimpleProduct('bricks-red-scotts-product-import');
        $global = $simple2->global();
        $global->setName("Bricks Red Scotts");
        $global->setPrice('89.00');
        $global->setCustomAttribute('color', 2);
        $global->setCustomAttribute('manufacturer', 1);

        $simple3 = new SimpleProduct('bricks-orange-scotts-product-import');
        $global = $simple3->global();
        $global->setName("Bricks Orange Scotts");
        $global->setPrice('90.00');
        $global->setCustomAttribute('color', 2);
        $global->setCustomAttribute('manufacturer', 2);

        $configurable = new ConfigurableProduct('scotts-product-import', ['color', 'manufacturer'], [
            $simple1,
            $simple2,
            $simple3
        ]);
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

        $superAttributeIds = self::$db->fetchSingleColumn("
            SELECT product_super_attribute_id
            FROM " . self::$metaData->superAttributeTable . "
            WHERE product_id = {$configurable->id}
            ORDER BY position
        ");

        // no change

        $importer->importConfigurableProduct($configurable);
        $importer->flush();

        $this->assertEquals([], $errors);
        $this->checkConfigurableData($configurable->id, $attributeData, $labelData, $linkData, $relationData);

        $newSuperAttributeIds = self::$db->fetchSingleColumn("
            SELECT product_super_attribute_id
            FROM " . self::$metaData->superAttributeTable . "
            WHERE product_id = {$configurable->id}
            ORDER BY position
        ");

        // the super attributes must not have been touched
        $this->assertEquals($newSuperAttributeIds, $superAttributeIds);

        // change super attribute and simples

        $configurable = new ConfigurableProduct('scotts-product-import', ['color'], [
            $simple1,
            $simple2
        ]);
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
        $data = self::$db->fetchAllNumber("
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

        $data = self::$db->fetchAllNumber("
            SELECT store_id, use_default, value
            FROM " . self::$metaData->superAttributeLabelTable . "
            WHERE product_super_attribute_id IN (" . implode(", ", $superAttributeIds) . ")
        ");

        $this->assertEquals($labelData, $data);

        $data = self::$db->fetchAllNumber("
            SELECT product_id, parent_id
            FROM " . self::$metaData->superLinkTable . "
            WHERE parent_id = {$configurableId}
        ");

        $this->assertEquals($linkData, $data);

        $data = self::$db->fetchAllNumber("
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
        $global = $product1->global();
        $global->setName("Christmas tree");
        $global->setPrice('98.00');
        $global->setSelectAttribute('color', 'white');
        $global->setMultipleSelectAttribute('color_group_product_importer', ['red', 'blue']);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals(["option white not found in attribute color", "option(s) red, blue not found in attribute color_group_product_importer"], $product1->getErrors());

        // auto create option

        $config->autoCreateOptionAttributes = ['color', 'tax_class_id', 'color_group_product_importer'];
        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("christmas-tree-product-import");
        $global = $product1->global();
        $global->setName("Christmas tree");
        $global->setPrice('98.00');
        $global->setSelectAttribute('color', 'grey');
        $global->setMultipleSelectAttribute('color_group_product_importer', ['red', 'blue']);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals([], $product1->getErrors());

        // option value color

        $colorAttributeId = self::$metaData->productEavAttributeInfo['color']->attributeId;
        $colorOptionId =  self::$metaData->productEavAttributeInfo['color']->optionValues['grey'];

        $value = self::$db->fetchSingleCell("
            SELECT value
            FROM " . self::$metaData->productEntityTable ."_int
            WHERE entity_id = {$product1->id} AND attribute_id = {$colorAttributeId} AND store_id = 0
        ");

        $this->assertEquals($colorOptionId, $value);

        // option values color_group

        $colorGroupAttributeId = self::$metaData->productEavAttributeInfo['color_group_product_importer']->attributeId;

        $colorGroupOptionId1 =  self::$metaData->productEavAttributeInfo['color_group_product_importer']->optionValues['red'];
        $colorGroupOptionId2 =  self::$metaData->productEavAttributeInfo['color_group_product_importer']->optionValues['blue'];

        $value = self::$db->fetchSingleCell("
            SELECT value
            FROM " . self::$metaData->productEntityTable ."_varchar
            WHERE entity_id = {$product1->id} AND attribute_id = {$colorGroupAttributeId} AND store_id = 0
        ");

        $this->assertEquals($colorGroupOptionId1 . ',' . $colorGroupOptionId2, $value);
    }

    /**
     * @throws Exception
     */
    public function testLinks()
    {
        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("christmas-angel-product-import");
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

        // do not specify links; the should not be removed

        $product1 = new SimpleProduct("christmas-angel-product-import");
        $global = $product1->global();
        $global->setName("Christmas angel");
        $global->setPrice('98.00');

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals($links, $this->getLinks($product1));

        // change the order of the related products. do not specify the other links: they should not be removed

        $product1 = new SimpleProduct("christmas-angel-product-import");
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

            $r = self::$db->fetchAllNumber("
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
    public function testTierPrices()
    {
        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product1 = new SimpleProduct("window-sill-modern-product-import");
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
            [$product1->id, 0, 0, 10, 12.25, 1],
            [$product1->id, 1, 0, 20, 12.15, 0],
        ];

        $this->assertEquals([], $product1->getErrors());
        $this->assertEquals($expected, $this->getTierPrices($product1->id));

        // no tier prices specified: do not remove tier prices

        $product1 = new SimpleProduct("window-sill-modern-product-import");

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals([], $product1->getErrors());
        $this->assertEquals($expected, $this->getTierPrices($product1->id));

        // update: change one entry (causes an insert and a delete) and update a price value (update)

        $product1 = new SimpleProduct("window-sill-modern-product-import");

        $product1->setTierPrices([
            new TierPrice(10, '12.25', 'General', 'base'),
            new TierPrice(20, '12.10'),
        ]);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $expected = [
            [$product1->id, 0, 1, 10, 12.25, 1],
            [$product1->id, 1, 0, 20, 12.10, 0],
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
        return self::$db->fetchAllNumber("
            SELECT entity_id, all_groups, customer_group_id, qty, value, website_id
            FROM " . self::$metaData->tierPriceTable . "
            WHERE entity_id = {$productId}
            ORDER BY qty
        ");
    }
}
