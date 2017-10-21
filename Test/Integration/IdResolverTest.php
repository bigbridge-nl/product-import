<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImporterFactory;
use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;
use BigBridge\ProductImport\Model\Resource\Id\NameToUrlKeyConverter;
use BigBridge\ProductImport\Model\Resource\IdResolver;
use Magento\Framework\App\ObjectManager;

/**
 * @author Patrick van Bergen
 */
class IdResolverTest extends \PHPUnit_Framework_TestCase
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

    public function testIdResolver()
    {
        /** @var IdResolver $resolver */
        $resolver = ObjectManager::getInstance()->get(IdResolver::class);

        $tests = [

            // category_ids

            // plain
            [['category_ids' => new References(["T-shirts", "Printed Clothing"])], true, ""],

            // attribute_set_id

            // plain
            [['attribute_set_id' => new Reference("Default")], true, ""],
            // corrupt
            [['attribute_set_id' => new Reference("Winograd")], false, "attribute set not found: Winograd"],

            // store_view_id

            // plain
            [['store_view_id' => new Reference("admin")], true, ""],
            // corrupt
            [['store_view_id' => new Reference("Mueller")], false, "store view not found: Mueller"],

            // tax_class_id

            // plain
            [['tax_class_id' => new Reference("Taxable Goods")], true, ""],
            // corrupt
            [['tax_class_id' => new Reference("Codd")], false, "tax class not found: Codd"],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct();
            $product->sku = "big-blue-box";
            $product->name = "Big Blue Box";
            $product->price = "123.00";
            $product->attribute_set_id = 4;

            foreach ($test[0] as $fieldName => $fieldValue) {
                $product->$fieldName = $fieldValue;
            }

            $resolver->resolveIds($product);
            $this->assertEquals($test[2], implode('; ', $product->errors));
            $this->assertEquals($test[1], $product->ok);

        }
    }

    public function testNameToUrlKeyConverter()
    {
        $converter = new NameToUrlKeyConverter();

        $this->assertSame("computers", $converter->createUrlKeyFromName("Computers"));
        $this->assertSame("computers-software", $converter->createUrlKeyFromName("Computers & Software"));
        $this->assertSame("un-velocipede", $converter->createUrlKeyFromName("Un vélocipède"));
        $this->assertSame("500", $converter->createUrlKeyFromName("€ 500"));
    }
}