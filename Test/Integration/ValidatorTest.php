<?php

namespace BigBridge\ProductImport\Test\Integration;

use IntlChar;
use Magento\Framework\App\ObjectManager;
use BigBridge\ProductImport\Api\ConfigurableProduct;
use BigBridge\ProductImport\Model\Resource\Validation\ConfigurableValidator;
use BigBridge\ProductImport\Api\ProductStockItem;
use BigBridge\ProductImport\Api\ProductStoreView;
use BigBridge\ProductImport\Model\Resource\Validation\Validator;
use BigBridge\ProductImport\Api\SimpleProduct;
use BigBridge\ProductImport\Api\ImporterFactory;

/**
 * @author Patrick van Bergen
 */
class ValidatorTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ImporterFactory */
    private static $factory;

    public static function setUpBeforeClass()
    {
        // include Magento
        require_once __DIR__ . '/../../../../../index.php';

        /** @var ImporterFactory $factory */
        self::$factory = ObjectManager::getInstance()->get(ImporterFactory::class);
    }

    public function testValidation()
    {
        /** @var Validator $validator */
        $validator = ObjectManager::getInstance()->get(Validator::class);

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
                    $product->setCategoryIds($fieldValue);
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

    public function testImageValidation()
    {
        /** @var Validator $validator */
        $validator = ObjectManager::getInstance()->get(Validator::class);

        $tests = [
            [__DIR__ . "/../images/duck1.jpg", ""],
            [__DIR__ . "/../images/sloth1.jpg", "File not found: " . __DIR__ . "/../images/sloth1.jpg"],
            [__DIR__ . "/../images/empty.jpg", "File is empty: " . __DIR__ . "/../images/empty.jpg"],
            [__DIR__ . "/../images/no-image.txt", "Filetype not allowed (use .jpg, .png or .gif): " . __DIR__ . "/../images/no-image.txt"],
            ["https://en.wikipedia.org/static/images/project-logos/enwiki.png", ""],
            ["https://en.wikipedia.org/static/images/project-logos/not-enwiki.png", "Image url returned 404 (Not Found): https://en.wikipedia.org/static/images/project-logos/not-enwiki.png"],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct('validator-product-import');
            $product->setAttributeSetId(4);

            $global = $product->global();
            $global->setName("Big Blue Box");
            $global->setPrice("123.00");
            $product->addImage($test[0]);

            $validator->validate($product);
            $this->assertEquals($test[1], implode('; ', $product->getErrors()));
        }

    }

    public function testStockItemValidation()
    {
        /** @var Validator $validator */
        $validator = ObjectManager::getInstance()->get(Validator::class);

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
                    $stock->setQuantity($value);
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
        /** @var ConfigurableValidator $configurableValidator */
        $configurableValidator = ObjectManager::getInstance()->get(ConfigurableValidator::class);
        /** @var Validator $validator */
        $validator = ObjectManager::getInstance()->get(Validator::class);

        $simple1 = new SimpleProduct('bricks-red-redweiser-product-import');
        $simple1->setAttributeSetId(4);
        $global = $simple1->global();
        $global->setName("Bricks Red Redweiser");
        $global->setPrice('99.00');

        $simple2 = new SimpleProduct('bricks-red-scotts-product-import');
        $simple2->setAttributeSetId(4);
        $global = $simple2->global();
        // note: missing name
        $global->setPrice('89.00');

        $simple3 = new SimpleProduct('bricks-orange-scotts-product-import');
        $simple3->setAttributeSetId(4);
        $global = $simple3->global();
        $global->setName("Bricks Orange Scotts");
        $global->setPrice('90.00');

        $configurable = new ConfigurableProduct('scotts-product-import', ['color', 'manufacturer'], [
            $simple1,
            $simple2,
            $simple3
        ]);
        $configurable->setAttributeSetId(4);
        $global = $configurable->global();
        $global->setName("Bricks");
        $global->setPrice('90.00');

        $validator->validate($simple1);
        $validator->validate($simple2);
        $validator->validate($simple3);
        $configurableValidator->validate($configurable);

        $this->assertSame(["These variants have errors: bricks-red-scotts-product-import"], $configurable->getErrors());
    }
}