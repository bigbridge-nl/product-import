<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Reader\ProductImportWebApiLogger;
use BigBridge\ProductImport\Model\Reader\XmlProductReader;

/**
 * @author Patrick van Bergen
 */
class ProductReaderTest extends \Magento\TestFramework\TestCase\AbstractController
{
    public function testProductReader()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var XmlProductReader $productReader */
        $productReader = $objectManager->create(XmlProductReader::class);

        $path = __DIR__ . "/resources/test-products.xml";

        $logger = new ProductImportWebApiLogger();

        $config = new ImportConfig();
        $config->resultCallback = [$logger, 'productImported'];

        $productReader->import($path, $config, false, $logger);

        echo $logger->getOutput();

        $this->assertSame(1, $logger->getOkProductCount());
    }
}