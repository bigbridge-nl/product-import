<?php

namespace BigBridge\ProductImport\Test;

use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;
use BigBridge\ProductImport\Model\Utils;
use BigBridge\ProductImport\Test\Resources\MySimpleProduct;

/**
 * Integration test
 *
 * @author Patrick van Bergen
 */
class ImportTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        require __DIR__ . '/../../../../index.php';

        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var ImporterFactory $factory */
        $factory = $om->get(ImporterFactory::class);

        /** @var Utils $utils */
        $utils = $om->get(Utils::class);

        $config = new ImportConfig();
        $config->batchSize = 200;

        $importer = $factory->create($config);

        $product = new MySimpleProduct();
        $product->name = "Big Blue Box";
        $product->sku = uniqid("bb");
        $product->attributeSetName = 'Default';

        $importer->insert($product);
        $importer->flush();

        $this->assertNotSame(null, $utils->getProductIdBySku($product->sku));
    }
}