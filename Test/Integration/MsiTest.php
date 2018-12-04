<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class MsiTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /** @var  ImporterFactory */
    private static $factory;

    /** @var MetaData */
    private static $metaData;

    public static function setUpBeforeClass()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);

        /** @var MetaData metaData */
        self::$metaData = $objectManager->get(MetaData::class);
    }

    /**
     * @throws \Exception
     */
    public function testInventorySourceItem()
    {
        if (version_compare(self::$metaData->magentoVersion, '2.3.0') < 0) {
            return;
        }

        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product = new SimpleProduct("my-msi-product");
        $product->setAttributeSetByName("Default");
        $product->global()->setName("My MSI Product");
        $product->global()->setPrice("12.35");

        $product->sourceItem("default")->setQty(100);
        $product->sourceItem("default")->setIsInStock(true);
        $product->sourceItem("default")->setNotifyStockQuantity(20);

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([], $product->getErrors());
    }

    /**
     * @throws \Exception
     */
    public function testResolveAndValidate()
    {
        if (version_compare(self::$metaData->magentoVersion, '2.3.0') < 0) {
            return;
        }

        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product = new SimpleProduct("my-msi-product");
        $product->setAttributeSetByName("Default");
        $product->global()->setName("My MSI Product");
        $product->global()->setPrice("12.35");

        $product->sourceItem("japan")->setQty(100);
        $product->sourceItem("default")->setQty("100,25");
        $product->sourceItem("default")->setNotifyStockQuantity("five");

        $importer->importSimpleProduct($product);

        $importer->flush();

        $this->assertEquals([
            "source code not found: japan",
            "source item quantity is not a decimal number with dot (100,25)",
            "source item notify_stock_qty is not a decimal number with dot (five)",
        ], $product->getErrors());
    }
}
