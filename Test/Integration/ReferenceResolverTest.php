<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;
use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;
use BigBridge\ProductImport\Model\Resource\Reference\NameToUrlKeyConverter;
use BigBridge\ProductImport\Model\Resource\ReferenceResolver;
use Magento\Framework\App\ObjectManager;

/**
 * @author Patrick van Bergen
 */
class ReferenceResolverTest extends \PHPUnit_Framework_TestCase
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

    public function testReferenceResolver()
    {
        $config = new ImportConfig();

        /** @var ReferenceResolver $resolver */
        $resolver = ObjectManager::getInstance()->get(ReferenceResolver::class);

        $tests = [

            // category_ids

            // plain
            [['category_ids' => ["T-shirts", "Printed Clothing"]], true, ""],

            // attribute_set_id

            // plain
            [['attribute_set_id' => new Reference("Default")], true, ""],
            // corrupt
            [['attribute_set_id' => new Reference("Winograd")], false, "attribute set name not found: Winograd"],

            // tax_class_id

            // plain
            [['tax_class_id' => "Taxable Goods"], true, ""],
            // corrupt
            [['tax_class_id' => "Codd"], false, "tax class name not found: Codd"],

            // web site ids

            // plain
            [['website_ids' => new References(["base"])], true, ""],
            // corrupt
            [['website_ids' => new References(["Shopaholic"])], false, "website code not found: Shopaholic"],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct("big-blue-box");
            $product->attribute_set_id = 4;

            $global = $product->global();
            $global->name = "Big Blue Box";
            $global->price = "123.00";

            foreach ($test[0] as $fieldName => $fieldValue) {

                if ($fieldName == 'tax_class_id') {
                    $product->global()->setTaxClassName($fieldValue);
                } elseif ($fieldName == 'category_ids') {
                    $product->setCategoriesByGlobalName($fieldValue);
                } elseif ($fieldName == 'website_ids') {
                    $product->global()->$fieldName = $fieldValue;
                } else {
                    $product->$fieldName = $fieldValue;
                }
            }

            $resolver->resolveIds($product, $config);
            $this->assertEquals($test[2], implode('; ', $product->getErrors()));
            $this->assertEquals($test[1], $product->isOk());

        }
    }

    public function testStoreViewResolver()
    {
        $config = new ImportConfig();

        /** @var ReferenceResolver $resolver */
        $resolver = ObjectManager::getInstance()->get(ReferenceResolver::class);

        $tests = [

            // plain
            ["admin", true, ""],
            // corrupt
            ["Mueller", false, "store view code not found: Mueller"],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct("big-blue-box");
            $product->attribute_set_id = 4;

            $global = $product->global();
            $global->name = "Big Blue Box";
            $global->price = "123.00";

            $product->storeView($test[0]);

            $resolver->resolveIds($product, $config);
            $this->assertEquals($test[2], implode('; ', $product->getErrors()));
            $this->assertEquals($test[1], $product->isOk());

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