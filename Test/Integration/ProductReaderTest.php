<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Reader\ProductImportWebApiLogger;
use BigBridge\ProductImport\Model\Reader\XmlProductReader;
use BigBridge\ProductImport\Model\Resource\MetaData;
use Exception;
use Magento\Framework\App\ObjectManager;

/**
 * @author Patrick van Bergen
 */
class ProductReaderTest extends \Magento\TestFramework\TestCase\AbstractController
{
    public function testProductReader()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var Magento2DbConnection $db */
        $db = $objectManager->get(Magento2DbConnection::class);

        /** @var MetaData $metaData */
        $metaData = $objectManager->create(MetaData::class);

        $this->setupAttributes($objectManager);

        /** @var XmlProductReader $productReader */
        $productReader = $objectManager->create(XmlProductReader::class);

        foreach (glob(__DIR__ . '/../../doc/example/*.xml') as $xmlFile) {

            if (basename($xmlFile) === "multi-source-inventory.xml") {
                if (version_compare($metaData->magentoVersion, "2.3.0") < 0) {
                    continue;
                }
                if (empty($db->fetchSingleCell("SHOW TABLES LIKE '" . $metaData->inventorySourceItem . "'"))) {
                    continue;
                }
            }
            if (basename($xmlFile) === "a-weee.xml") {
                if ($metaData->weeeAttributeId === null) {
                    continue;
                }
            }

            if (basename($xmlFile) === "custom-attributes.xml") {
                continue;
            }

            $success = true;

            $logger = new ProductImportWebApiLogger();

            try {

                $config = new ImportConfig();
                $config->imageSourceDir = __DIR__ . '/../../doc/example';
                $config->autoCreateOptionAttributes = ['color', 'manufacturer', 'color_group'];
                $config->resultCallback = [$logger, 'productImported'];

                $productReader->import($xmlFile, $config, false, $logger);

                if ($logger->getFailedProductCount() > 0) {
                    $success = false;
                }

            } catch (Exception $exception) {
                $success = false;
            }

            if (!$success) {
                echo "\n";
                echo "Error in " . $xmlFile . ":\n";
                echo $logger->getOutput() . "\n";
            } elseif ($logger->getOkProductCount() == 0) {
                echo $logger->getOutput();
            }

            $this->assertSame(0, $logger->getFailedProductCount());
            $this->assertGreaterThan(0, $logger->getOkProductCount());
        }
    }

    private function setupAttributes(ObjectManager $objectManager)
    {
        $metaData = $objectManager->get(MetaData::class);
        $db = $objectManager->get(Magento2DbConnection::class);

        // create a multiple select attribute
        $db->execute("
            REPLACE INTO " . $metaData->attributeTable . "
            SET 
                entity_type_id = " . $metaData->productEntityTypeId . ",
                attribute_code = 'color_group_product_importer',
                frontend_input = 'multiselect',
                backend_type = 'varchar'
        ");

        $insertId = $db->getLastInsertId();

        $metaData->productEavAttributeInfo['color_group'] =
            new EavAttributeInfo('color_group', $insertId, false, 'varchar', 'catalog_product_entity_varchar', 'multiselect', 1);

    }
}
