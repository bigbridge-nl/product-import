<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Model\Data\Product;
use IntlChar;
use BigBridge\ProductImport\Model\Resource\Validator;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;
use SimpleXMLElement;

/**
 * @author Patrick van Bergen
 */
class ValidatorTest extends \PHPUnit_Framework_TestCase
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

    public function testValidation()
    {
        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price', 'status', 'description', 'special_from_date'];

        list($importer, $error) = self::$factory->create($config);

        /** @var Validator $validator */
        $validator = ObjectManager::getInstance()->get(Validator::class);
        $validator->setConfig($config);

        $tests = [

            /* data types */

            // varchar

            // plain
            [['name' => 'Big Blue Box'], true, ""],
            // corrupt
            [['name' => new SimpleXMLElement("<xml></xml>")], false, "name is an object (SimpleXMLElement), should be a string"],
            // full
            [['name' => str_repeat('-', 255)], true, ""],
            // overflow
            [['name' => str_repeat('-', 256)], false, "name has 256 characters (max 255)"],

            // text

            // plain
            [['description' => 'A nice box for lots of things'], true, ""],
            // corrupt
            [['description' => new SimpleXMLElement("<xml></xml>")], false, "description is an object (SimpleXMLElement), should be a string"],
            // full
            [['description' => str_repeat('-', 65536)], true, ""],
            // overflow
            [['description' => str_repeat('-', 65537)], false, "description has 65537 bytes (max 65536)"],

            // date time

            // plain
            [['special_from_date' => '2017-10-14 01:34:18'], true, ""],
            // corrupt
            [['special_from_date' => '2017-10-14'], false, "special_from_date is not a MySQL date time (2017-10-14)"],
            [['special_from_date' => new SimpleXMLElement("<xml></xml>")], false, "special_from_date is an object (SimpleXMLElement), should be a string"],

            // int

            // plain
            [['status' => Product::STATUS_ENABLED], true, ""],
            [['status' => "2"], true, ""],
            // corrupt
            [['status' => 'Enabled'], false, "status is not an integer (Enabled)"],
            [['status' => new SimpleXMLElement("<xml></xml>")], false, "status is an object (SimpleXMLElement), should be a string"],

            // decimal

            // plain
            [['price' => '123.95'], true, ""],
            [['price' => '-123.95'], false, "price is not a positive decimal number with dot (-123.95)"],
            // corrupt
            [['price' => '123,95'], false, "price is not a positive decimal number with dot (123,95)"],
            [['price' => 123.,95], false, "price is a double, should be a string"],
            [['price' => new SimpleXMLElement("<xml></xml>")], false, "price is an object (SimpleXMLElement), should be a string"],

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
            // corrupt
            [['sku' => array()], false, "sku is a array, should be a string"],

            // name

            // missing
            [['name' => ''], false, "missing name"],

            // attribute set name

            // missing
            [['attribute_set_name' => ''], false, "missing attribute set name"],

            // store view code

            // missing
            [['store_view_code' => ''], false, "missing store view code"],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct();
            $product->sku = "big-blue-box";
            $product->name = "Big Blue Box";
            $product->price = "123.00";

            foreach ($test[0] as $fieldName => $fieldValue) {
                $product->$fieldName = $fieldValue;
            }

            list($ok, $error) = $validator->validate($product);
            $this->assertEquals($test[2], $error);
            $this->assertEquals($test[1], $ok);
        }
    }
}