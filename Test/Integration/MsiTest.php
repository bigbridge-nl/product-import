<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
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

    /** @var Magento2DbConnection */
    protected static $db;

    public static function setUpBeforeClass(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);

        /** @var MetaData metaData */
        self::$metaData = $objectManager->get(MetaData::class);

        /** @var Magento2DbConnection db */
        self::$db = $objectManager->get(Magento2DbConnection::class);
    }

    /**
     * @throws \Exception
     */
    public function testInventorySourceItem()
    {
        if (version_compare(self::$metaData->magentoVersion, '2.3.0') < 0) {
            return;
        }

        if (empty(self::$db->fetchSingleCell("SHOW TABLES LIKE '" . self::$metaData->inventorySourceItem . "'"))) {
            return;
        }

        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product = new SimpleProduct("my-msi-product");
        $product->setAttributeSetByName("Default");
        $product->global()->setName("My MSI Product");
        $product->global()->setPrice("12.35");

        $product->sourceItem("default")->setQuantity(100);
        $product->sourceItem("default")->setStatus(1);
        $product->sourceItem("default")->setNotifyStockQuantity(20);

        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertEquals(['quantity' => 100, 'status' => 1], $this->loadSourceItemData("my-msi-product"));
        $this->assertEquals(['notify_stock_qty' => 20], $this->loadNotificationData("my-msi-product"));

        $product->sourceItem("default")->setNotifyStockQuantity(30);

        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertEquals(['quantity' => 100, 'status' => 1], $this->loadSourceItemData("my-msi-product"));
        $this->assertEquals(['notify_stock_qty' => 30], $this->loadNotificationData("my-msi-product"));

        $product->sourceItem("default")->setStatus(0);

        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertEquals(['quantity' => 100, 'status' => 0], $this->loadSourceItemData("my-msi-product"));
        $this->assertEquals(['notify_stock_qty' => 30], $this->loadNotificationData("my-msi-product"));
    }

    protected function loadSourceItemData(string $sku)
    {
        return self::$db->fetchRow("
            SELECT quantity, status
            FROM " . self::$metaData->inventorySourceItem . "
            WHERE sku = ? and source_code = ?
        ", [
            $sku, "default"
        ]);
    }

    protected function loadNotificationData(string $sku)
    {
        return self::$db->fetchRow("
            SELECT notify_stock_qty
            FROM " . self::$metaData->inventoryLowStockNotificationConfiguration . "
            WHERE sku = ? and source_code = ?
        ", [
            $sku, "default"
        ]);
    }

    /**
     * @throws \Exception
     */
    public function testResolveAndValidate()
    {
        if (version_compare(self::$metaData->magentoVersion, '2.3.0') < 0) {
            return;
        }

        if (empty(self::$db->fetchSingleCell("SHOW TABLES LIKE '" . self::$metaData->inventorySourceItem . "'"))) {
            return;
        }

        $config = new ImportConfig();
        $importer = self::$factory->createImporter($config);

        $product = new SimpleProduct("my-msi-product");
        $product->setAttributeSetByName("Default");
        $product->global()->setName("My MSI Product");
        $product->global()->setPrice("12.35");

        $product->sourceItem("japan")->setQuantity(100);
        $product->sourceItem("default")->setQuantity("100,25");
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
