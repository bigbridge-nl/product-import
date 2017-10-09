<?php

namespace BigBridge\ProductImport\Test;

use Magento\Catalog\Api\ProductRepositoryInterface;
use BigBridge\ProductImport\Model\Data\SimpleProduct;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;
use BigBridge\ProductImport\Model\Utils;

/**
 * Integration test
 *
 * @author Patrick van Bergen
 */
class ImportTest extends \PHPUnit_Framework_TestCase
{
    public function testInsert()
    {
        require __DIR__ . '/../../../../index.php';

        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var ImporterFactory $factory */
        $factory = $om->get(ImporterFactory::class);

        /** @var ProductRepositoryInterface $repository */
        $repository = $om->get(ProductRepositoryInterface::class);

        /** @var Utils $utils */
        $utils = $om->get(Utils::class);

        $config = new ImportConfig();
        $config->batchSize = 200;
        $config->eavAttributes = ['name'];

        $importer = $factory->create($config);

        $sku1 = uniqid("bb");
        $sku2 = uniqid("bb");
        $sku3 = uniqid("bb");

        $products = [
            ["Big Blue Box", $sku1, 'Default'],
            ["Big Yellow Box", null, 'Default'],
            ["Big Red Box", $sku2, 'Default'],
            [null, '', ''],
            ["Big Blue Box", $sku3, 'Boxes'],
        ];

        $results = [];

        foreach ($products as $data) {
            $product = new SimpleProduct();
            $product->name = $data[0];
            $product->sku = $data[1];
            $product->attributeSetName = $data[2];

            list($ok, $error) = $importer->insert($product);

            $results[] = [$ok, $error];
        }

        $importer->flush();

        $product1 = $repository->get($sku1);
        $this->assertTrue($product1->getAttributeSetId() > 0);
        $this->assertEquals($products[0][0], $product1->getName());

        $product2 = $repository->get($sku2);
        $this->assertTrue($product2->getAttributeSetId() > 0);
        $this->assertEquals($products[2][0], $product2->getName());

        $expected = [
            [true, ""],
            [false, "missing sku"],
            [true, ""],
            [false, "missing sku; missing name; missing attribute set name"],
            [false, "unknown attribute set name: Boxes"]
        ];

        $this->assertEquals($expected, $results);
    }
}
