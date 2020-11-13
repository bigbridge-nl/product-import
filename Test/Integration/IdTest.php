<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class IdTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /** @var  ImporterFactory */
    private static $factory;

    /** @var  Magento2DbConnection */
    protected static $db;

    /** @var  Metadata */
    protected static $metadata;

    public static function setUpBeforeClass(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);

        /** @var Magento2DbConnection $db */
        self::$db = $objectManager->get(Magento2DbConnection::class);

        self::$metadata = $objectManager->get(MetaData::class);
    }

    /**
     * @throws \Exception
     */
    public function testUpdateById()
    {
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        // create an object
        $product1 = new SimpleProduct('identity-product-import');
        $product1->setAttributeSetByName("Default");

        $global = $product1->global();
        $global->setName("Identity is the Name");
        $global->setPrice('99.95');

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals([], $errors);

        // ---

        // create second object, same id, different sku
        $product2 = new SimpleProduct('new-identity-product-import');
        $product2->id = $product1->id;

        // change the name
        $product2->global()->setName("IDentity is the Name");

        $importer->importSimpleProduct($product2);
        $importer->flush();

        $this->assertEquals([], $errors);

        // ---

        // if we now import the object with the new sku, we get the original id
        $product3 = new SimpleProduct('new-identity-product-import');
        $importer->importSimpleProduct($product3);
        $importer->flush();

        $this->assertEquals([], $errors);

        $this->assertSame($product1->id, $product3->id);

        // ---

        // non-existing id should create error
        $product4 = new SimpleProduct('new-identity-product-import');
        $product4->id = -1;
        $importer->importSimpleProduct($product4);
        $importer->flush();

        $this->assertEquals(['Id does not belong to existing product: -1'], $errors);
    }
}
