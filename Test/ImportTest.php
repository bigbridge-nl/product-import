<?php

namespace BigBridge\ProductImport\Test;

use BigBridge\ProductImport\Model\Data\SimpleProduct;
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

        $sku1 = uniqid("bb");
        $sku2 = uniqid("bb");

        $products = [
            ["Big Blue Box", $sku1, 'Default'],
            ["Big Yellow Box", null, 'Default'],
            ["Big Red Box", $sku2, 'Default'],
        ];

        $results = [];

        /** @var SimpleProduct $product */
        foreach ($products as $data) {
            $product = new MySimpleProduct();
            $product->name = $data[0];
            $product->sku = $data[1];
            $product->attributeSetName = $data[2];
            list($ok, $error) = $importer->insert($product);
            $results[] = [$ok, $error];
        }

        $importer->flush();

        $this->assertNotEquals(null, $utils->getProductIdBySku($sku1));
        $this->assertNotEquals(null, $utils->getProductIdBySku($sku2));

        $this->assertEquals([[true, ""], [false, "Missing SKU"], [true, ""]], $results);
    }
}
