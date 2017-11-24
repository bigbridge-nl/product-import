<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Data\ProductStoreView;
use BigBridge\ProductImport\Model\Reference;
use IntlChar;
use BigBridge\ProductImport\Model\Resource\Validator;
use Magento\Framework\App\ObjectManager;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImporterFactory;
use SimpleXMLElement;

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
            [['price' => '-123.95'], false, "price is not a positive decimal number with dot (-123.95)"],
            // corrupt
            [['price' => '123,95'], false, "price is not a positive decimal number with dot (123,95)"],

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
            // missing
            [['attribute_set_id' => ''], false, "missing attribute set id"],
            // corrupt
            [['attribute_set_id' => 'Lost Boys'], false, "attribute set id is a string, should be an integer"],

            // category_ids

            // plain
            [['category_ids' => [1, 2]], true, ""],
            [['category_names' => ["Hardware", "Software"]], true, ""],
            // corrupt
            [['category_ids' => ["1, 2"]], false, "category_ids should be an array of integers"],

            // website_ids

            // plain
            [['website_ids' => [1]], true, ""],
            // corrupt
            [['website_ids' => 1], false, "website_ids is a integer, should be a References object or an array of integers"],
            [['website_ids' => ["Shopaholic", "Wannabuy"]], false, "website_ids should be a References object or an array of integers"],
            [['website_ids' => new Reference("Shoparound")], false, "website_ids is a Reference, should be a References(!) object"],
        ];

        foreach ($tests as $test) {

            $sku = (isset($test[0]['sku']) ? $test[0]['sku'] : "big-blue-box");

            $product = new SimpleProduct($sku);
            $product->attribute_set_id = 4;

            $global = $product->global();
            $global->setName("Big Blue Box");
            $global->setPrice("123.00");

            foreach ($test[0] as $fieldName => $fieldValue) {
                if (in_array($fieldName, ['attribute_set_id'])) {
                    $product->$fieldName = $fieldValue;
                } elseif ($fieldName == 'category_ids') {
                    $product->setCategoryIds($fieldValue);
                } elseif ($fieldName == 'category_names') {
                    $product->setCategoriesByGlobalName($fieldValue);
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
                } else {
                    $global->$fieldName = $fieldValue;
                }
            }

            $validator->validate($product);
            $this->assertEquals($test[2], implode('; ', $product->errors));
            $this->assertEquals($test[1], $product->ok);
        }
    }
}