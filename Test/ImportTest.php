<?php

namespace BigBridge\ProductImport\Test;

use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\Importer;
use BigBridge\ProductImport\Model\ImporterFactory;
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

        $config = new ImportConfig();

        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $factory = $om->get(ImporterFactory::class);
        $importer = $factory->create($config);

        $product = new MySimpleProduct();
        $product->name = "Big Blue Box";
        $product->sku = "bb1103";

        $importer->importSimpleProduct($product);
        $importer->completeFullImport();
    }
}