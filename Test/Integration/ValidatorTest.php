<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\Data\CustomOption;
use BigBridge\ProductImport\Api\Data\CustomOptionValue;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Validation\ConfigurableValidator;
use BigBridge\ProductImport\Model\Resource\Validation\Validator;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\ProductStockItem;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\ImporterFactory;
use IntlChar;

/**
 * @author Patrick van Bergen
 */
class ValidatorTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /** @var  ImporterFactory */
    private static $factory;

    public static function setUpBeforeClass(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);
    }

    public function testValidation()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var Validator $validator */
        $validator = $objectManager->get(Validator::class);

        $tests = [

            /* data types */

            // varchar

            // plain
            [['name' => 'Big Blue Box'], true, ""],
            // full
            [['name' => str_repeat('-', 255)], true, ""],
            // overflow
            [['name' => str_repeat('-', 256)], false, "name has 256 characters (max 255)"],

            // text

            // plain
            [['description' => 'A nice box for lots of things'], true, ""],
            // full
            [['description' => str_repeat('-', 65536)], true, ""],
            // overflow
            [['description' => str_repeat('-', 65537)], false, "description has 65537 bytes (max 65536)"],

            // date time

            // plain
            [['special_from_date' => '2017-10-14 01:34:18'], true, ""],
            [['special_from_date' => '2017-10-14'], true, ""],
            // corrupt
            [['special_from_date' => 'October 4, 2017'], false, "special_from_date is not a MySQL date or date time (October 4, 2017)"],

            // int

            // plain
            [['status' => ProductStoreView::STATUS_ENABLED], true, ""],
            [['status' => 2], true, ""],

            // decimal

            // plain
            [['price' => '123.95'], true, ""],
            [['price' => '.99'], true, ""],
            // corrupt
            [['price' => '123,95'], false, "price is not a decimal number with dot (123,95)"],

            /* non-eav fields */

            // sku

            // plain
            [['sku' => 'big-red-box'], true, ""],
            // missing
            [['sku' => ''], false, "missing sku"],
            // full
            [['sku' => str_repeat('x', 64)], true, ""],
            [['sku' => '<' . str_repeat(IntlChar::chr(0x010F), 62) . '>'], true, ""],
            // overflow
            [['sku' => str_repeat('x', 65)], false, "sku has 65 characters (max 64)"],

            // name

            // missing
            [['name' => ''], false, "missing name"],

            // attribute set id

            // plain
            [['attribute_set_id' => 4], true, ""],

            // category_ids

            // plain
            [['category_ids' => [1, 2]], true, ""],
            // corrupt
            [['category_ids' => ["1, 2"]], false, "category_ids should be an array of integers"],

            // website_ids

            // plain
            [['website_ids' => [1]], true, ""],
            // corrupt
            [['website_ids' => [null]], false, "website_ids should be an array of integers"],

            // custom attribute

            // corrupt
            [['number_of_legs' => '11'], false, "attribute does not exist: number_of_legs"],
        ];

        foreach ($tests as $test) {

            $sku = (isset($test[0]['sku']) ? $test[0]['sku'] : "big-blue-box");

            $product = new SimpleProduct($sku);
            $product->setAttributeSetId(4);

            $global = $product->global();
            $global->setName("Big Blue Box");
            $global->setPrice("123.00");

            foreach ($test[0] as $fieldName => $fieldValue) {
                if ($fieldName == 'attribute_set_id') {
                    $product->setAttributeSetId($fieldValue);
                } elseif ($fieldName == 'category_ids') {
                    $product->addCategoryIds($fieldValue);
                } elseif ($fieldName == 'website_ids') {
                    $product->setWebsitesIds($fieldValue);
                } elseif ($fieldName == 'name') {
                    $global->setName($fieldValue);
                } elseif ($fieldName == 'price') {
                    $global->setPrice($fieldValue);
                } elseif ($fieldName == 'description') {
                    $global->setDescription($fieldValue);
                } elseif ($fieldName == 'status') {
                    $global->setStatus($fieldValue);
                } elseif ($fieldName == 'special_from_date') {
                    $global->setSpecialFromDate($fieldValue);
                } elseif ($fieldName == 'special_to_date') {
                    $global->setSpecialToDate($fieldValue);
                } elseif ($fieldName == 'number_of_legs') {
                    $global->setCustomAttribute($fieldName, $fieldValue);
                }
            }

            $validator->validate($product);
            $this->assertEquals($test[2], implode('; ', $product->getErrors()));
            $this->assertEquals($test[1], $product->isOk());
        }
    }

    /**
     * @throws \Exception
     */
    public function testStoreViewCode()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        $factory = $objectManager->get(ImporterFactory::class);
        $config = new ImportConfig();

        $importer = $factory->createImporter($config);

        $product = new SimpleProduct('wherefrom');
        $product->setAttributeSetId(4);

        $global = $product->global();
        $global->setName("Where from?");
        $global->setPrice("123.00");
        $global->generateUrlKey();

        $greenRoom = $product->storeView('green-room');
        $greenRoom->setPrice('18.00');
        $greenRoom->setName("Waarheen?");
        $greenRoom->generateUrlKey();

        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertSame(["store view code not found: green-room"], $product->getErrors());

    }

    /**
     * @throws \Exception
     */
    public function testImageValidation()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        $factory = $objectManager->get(ImporterFactory::class);
        $config = new ImportConfig();

        $config->existingImageStrategy = ImportConfig::EXISTING_IMAGE_STRATEGY_HTTP_CACHING;

        $importer = $factory->createImporter($config);

        $tests = [
            [__DIR__ . "/../images/duck1.jpg", ""],
            [__DIR__ . "/../images/sloth1.jpg", "File not found: " . __DIR__ . "/../images/sloth1.jpg"],
            [__DIR__ . "/../images/empty.jpg", "File is empty: " . __DIR__ . "/../images/empty.jpg"],
            [__DIR__ . "/../images/no-image.txt", "Filetype not allowed (use .jpg, .png or .gif): " . __DIR__ . "/../images/no-image.txt"],
            ["https://en.wikipedia.org/static/images/project-logos/not-enwiki.png", "Image url returned 404 (Not Found): https://en.wikipedia.org/static/images/project-logos/not-enwiki.png"],
            ["https://en.wikipedia.org/static/images/project-logos/enwiki.png", ""],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct('validator-product-import');
            $product->setAttributeSetId(4);

            $global = $product->global();
            $global->setName("Big Blue Box");
            $global->setPrice("123.00");
            $product->addImage($test[0]);

            $importer->importSimpleProduct($product);
            $importer->flush();

            $this->assertEquals($test[1], implode('; ', $product->getErrors()));
        }
    }

    public function testImageRoleValidation()
    {
        @unlink(BP . '/pub/media/catalog/product/d/u/duck1.jpg');

        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var Validator $validator */
        $validator = $objectManager->get(Validator::class);

        $product = new SimpleProduct('validator-product-import');
        $product->setAttributeSetId(4);

        $global = $product->global();
        $global->setName("Big Blue Box");
        $global->setPrice("123.00");

        $image = $product->addImage(__DIR__ . "/../images/duck1.jpg");
        $product->global()->setImageRole($image, 'not-an-attribute');
        $product->global()->setImageRole($image, 'name');

        $validator->validate($product);
        $this->assertEquals([
            "Image role attribute does not exist: not-an-attribute",
            "Image role attribute input type is not media image: name"
        ], $product->getErrors());
    }

    public function testStockItemValidation()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var Validator $validator */
        $validator = $objectManager->get(Validator::class);

        $tests = [
            [['qty' => 'nul'], "qty is not a decimal number with dot (nul)"],
            [['low_stock_date' => '4th of July'], "low_stock_date is not a MySQL date or date time (4th of July)"],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct('validator-product-import');
            $product->setAttributeSetId(4);

            $global = $product->global();
            $global->setName("Big Blue Box");
            $global->setPrice("123.00");

            $stock = $product->defaultStockItem();

            foreach ($test[0] as $name => $value) {
                if ($name == ProductStockItem::QTY) {
                    $stock->setQty($value);
                } elseif ($name == ProductStockItem::LOW_STOCK_DATE) {
                    $stock->setLowStockDate($value);
                }
            }

            $validator->validate($product);
            $this->assertEquals($test[1], implode('; ', $product->getErrors()));
        }
    }

    public function testConfigurableValidator()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ConfigurableValidator $configurableValidator */
        $configurableValidator = $objectManager->get(ConfigurableValidator::class);

        // ----

        $configurable = new ConfigurableProduct('scotts-product-import');
        $configurable->setAttributeSetId(4);
        $global = $configurable->global();
        $global->setName("Bricks");
        $global->setPrice('90.00');

        $configurableValidator->validate($configurable, []);

        $this->assertSame([
            "specify the super attributes with setSuperAttributeCodes()",
            "specify the variants with setVariantSkus()"
        ], $configurable->getErrors());
    }

    public function testTierPrices()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $product = new SimpleProduct('big-blue-box');
        $product->setAttributeSetId(4);

        $global = $product->global();
        $global->setName("Big Blue Box");
        $global->setPrice("123.00");

        $product->setTierPrices([[10, 12.95, "Not Logged In", "Clothing"], [20, 12.75, "Not Logged In", "Clothing"]]);

        /** @var Validator $validator */
        $validator = $objectManager->get(Validator::class);

        $validator->validate($product);

        $this->assertSame(["tierprices should be an array of TierPrice"], $product->getErrors());
    }

    public function testCustomOptions()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $product = new SimpleProduct('big-blue-box');
        $product->setAttributeSetId(4);

        $global = $product->global();
        $global->setName("Big Blue Box");
        $global->setPrice("123.00");

        $product->setCustomOptions([
            $color = CustomOption::createCustomOptionDropDown(true, ["red", "green", "blue"]),
        ]);

        $product->global()->setCustomOptionTitle($color, "Color");
        $product->global()->setCustomOptionValues($color, [
            new CustomOptionValue("0.10", ProductStoreView::PRICE_TYPE_FIXED, 'Red'),
            new CustomOptionValue("0.15", ProductStoreView::PRICE_TYPE_FIXED, 'Green')
        ]);

        /** @var Validator $validator */
        $validator = $objectManager->get(Validator::class);

        $validator->validate($product);

        $this->assertSame(["Custom option with values [red, green, blue] has an incorrect number of values in store view 'admin'"], $product->getErrors());

    }
}
