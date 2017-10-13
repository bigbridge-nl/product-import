<?php

namespace BigBridge\ProductImport\Test;

use BigBridge\ProductImport\Model\Data\Product;
use IntlChar;
use BigBridge\ProductImport\Model\Resource\Validator;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;

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
        require_once __DIR__ . '/../../../../index.php';

        /** @var ImporterFactory $factory */
        self::$factory = ObjectManager::getInstance()->get(ImporterFactory::class);

        /** @var ProductRepositoryInterface $repository */
        self::$repository = ObjectManager::getInstance()->get(ProductRepositoryInterface::class);
    }

    public function testConfig()
    {
        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price'];

        list($importer, $error) = self::$factory->create($config);

        $this->assertNotNull($importer);
        $this->assertEquals("", $error);

        // --------------------

        $config = new ImportConfig();
        $config->eavAttributes = null;

        list($importer, $error) = self::$factory->create($config);

        $this->assertNull($importer);
        $this->assertEquals("config: eavAttributes is not an array", $error);

        // --------------------

        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price', 0.1];

        list($importer, $error) = self::$factory->create($config);

        $this->assertNull($importer);
        $this->assertEquals("config: eavAttributes should be strings", $error);

        // --------------------

        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price', 'shrdlu', 'sasquatch'];

        list($importer, $error) = self::$factory->create($config);

        $this->assertNull($importer);
        $this->assertEquals("config: eavAttributes: not an eav attribute: shrdlu, sasquatch", $error);
    }

    public function testValidation()
    {
        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price', 'status'];

        list($importer, $error) = self::$factory->create($config);

        $sku1 = uniqid("bb");
        $sku2 = uniqid("bb");
        $sku3 = uniqid("bb");
        $sku4 = uniqid("bb");
        $sku5 = uniqid("bb");
        $sku6 = uniqid("bb");

        $products = [
            ["Big Blue Box", $sku1, 'Default', '3.25', 1],
            // NB: erroneous 3 is not checked
            ["Big Yellow Box", null, 'Default', '4.00', 3],
            ["Big Red Box", $sku2, 'Default', '127.95', "2"],
            [null, ' ', "\n", null, "Enabled"],
            ["Big Blue Box", $sku3, 'Boxes', '11.45', 1],
            ["Big Orange Box " . str_repeat('-', 241), $sku4, 'Default', '11,45', 1],
            ["Big Pink Box", $sku5, 'Default', 11.45, 1],
            ["Big Turquoise Box", $sku5, 'Default', new \SimpleXMLElement("<xml></xml>"), 1],
            // extra whitespace
            [" Big Empty Box ", " " . $sku6 . " ", ' Default ', ' 127.95 ', 1],
            ["Large Box", "1234567890123456789012345678901234567890123456789012345678901234", ' Default ', '10.00', 1],
            ["Too Large Box 1", "12345678901234567890123456789012345678901234567890123456789012345", ' Default ', '10.00', 1],
            // 64 2-byte chars is ok
            ["Large Box 2", '<' . str_repeat(IntlChar::chr(0x010F), 62) . '>', ' Default ', '10.00', 1],
        ];

        $results = [];

        foreach ($products as $data) {
            $product = new SimpleProduct();
            $product->name = $data[0];
            $product->sku = $data[1];
            $product->attributeSetName = $data[2];
            $product->price = $data[3];
            $product->status = $data[4];

            list($ok, $error) = $importer->insert($product);

            $results[] = [$ok, $error];
        }

        $importer->flush();

        if ($results[0][0]) {
            $product1 = self::$repository->get($sku1);
            $this->assertTrue($product1->getAttributeSetId() > 0);
            $this->assertEquals($products[0][0], $product1->getName());
            $this->assertEquals($products[0][3], $product1->getPrice());
            $this->assertEquals($products[0][4], $product1->getStatus());
        }

        if ($results[2][0]) {
            $product2 = self::$repository->get($sku2);
            $this->assertTrue($product2->getAttributeSetId() > 0);
            $this->assertEquals($products[2][0], $product2->getName());
            $this->assertEquals($products[2][3], $product2->getPrice());
            $this->assertEquals($products[2][4], $product2->getStatus());
        }

        if ($results[8][0]) {
            $product6 = self::$repository->get(trim($sku6));
            $this->assertTrue($product6->getAttributeSetId() > 0);
            $this->assertEquals(trim($products[8][0]), $product6->getName());
            $this->assertEquals(trim($products[8][3]), $product6->getPrice());
        }

        $expected = [
            [true, ""],
            [false, "missing sku"],
            [true, ""],
            [false, "missing sku; missing attribute set name; missing name; missing price; status is not an integer (Enabled)"],
            [false, "unknown attribute set name: Boxes"],
            [false, "name has 256 characters (max 255); price is not a decimal number (11,45)"],
            [false, "price is a double, should be a string"],
            [false, "price is an object (SimpleXMLElement), should be a string"],
            [true, ""],
            [true, ""],
            [false, "sku exceeds " . Validator::SKU_MAX_LENGTH . " characters"],
            [true, ""],
        ];

        $this->assertEquals($expected, $results);
    }

    public function testEmptyFields()
    {
        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'color', 'special_price'];

        list($importer, $error) = self::$factory->create($config);

        $sku1 = uniqid("bb");

        $product = new SimpleProduct();
        $product->name = "Big Purple Box";
        $product->sku = $sku1;
        $product->attributeSetName = "Default";
        $product->special_price = null;
        // note: color is missing completely

        list($ok, $error) = $importer->insert($product);

        echo $error;

        $this->assertTrue($ok);

        $importer->flush();

        $product1 = self::$repository->get($sku1);
        $this->assertEquals(null, $product1->getPrice());
    }

    public function testUpdate()
    {
        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price'];

        list($importer, $error) = self::$factory->create($config);

        $sku1 = uniqid("bb");
        $sku2 = uniqid("bb");

        $products = [
            ["Big Blue Box", $sku1, 'Default', '3.25', 'admin'],
            ["Big Yellow Box", $sku2, 'Default', '4.00', 'admin'],
            ["Grote Gele Doos", $sku2, 'Default', '4.25', 'default'],
        ];

        $results = [];

        foreach ($products as $data) {
            $product = new SimpleProduct();
            $product->name = $data[0];
            $product->sku = $data[1];
            $product->attributeSetName = $data[2];
            $product->price = $data[3];
            $product->storeViewCode = $data[4];

            list($ok, $error) = $importer->insert($product);

            $results[] = [$ok, $error];
        }

        $importer->flush();

        $products2 = [
            ["Big Blueish Box", $sku1, 'Default', '3.45', 'admin'],
            ["Big Yellowish Box", $sku2, 'Default', '3.95', 'admin'],
            ["Grote Gelige Doos", $sku2, 'Default', '4.30', 'default'],
        ];

        foreach ($products2 as $data) {
            $product = new SimpleProduct();
            $product->name = $data[0];
            $product->sku = $data[1];
            $product->attributeSetName = $data[2];
            $product->price = $data[3];
            $product->storeViewCode = $data[4];

            list($ok, $error) = $importer->insert($product);

            $results[] = [$ok, $error];
        }

        $importer->flush();

        $product1 = self::$repository->get($sku1);
        $this->assertTrue($product1->getAttributeSetId() > 0);
        $this->assertEquals($products2[0][0], $product1->getName());
        $this->assertEquals($products2[0][3], $product1->getPrice());

        $product2 = self::$repository->get($sku2, false, 0);
        $this->assertTrue($product2->getAttributeSetId() > 0);
        $this->assertEquals($products2[1][0], $product2->getName());
        $this->assertEquals($products2[1][3], $product2->getPrice());

        $product2a = self::$repository->get($sku2, false, 1);
        $this->assertTrue($product2a->getAttributeSetId() > 0);
        $this->assertEquals($products2[2][0], $product2a->getName());
        $this->assertEquals($products2[2][3], $product2a->getPrice());
    }
}
