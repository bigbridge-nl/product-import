<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\Importer;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\ProductDeleter;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;

/**
 * @author Patrick van Bergen
 */
class ProductDeleterTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /**
     * @throws \Exception
     */
    public function testDeleteProductsById()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var Magento2DbConnection $db */
        $db = $objectManager->get(Magento2DbConnection::class);

        $factory = $objectManager->get(ImporterFactory::class);
        $config = new ImportConfig();

        /** @var Importer $importer */
        $importer = $factory->createImporter($config);

        /** @var ProductDeleter $deleter */
        $deleter = $objectManager->get(ProductDeleter::class);

        $productCount = $db->fetchSingleCell("
            SELECT COUNT(*) FROM catalog_product_entity
        ");

        // add product
        $product = new SimpleProduct("one-day-fly " . uniqid());
        $product->setAttributeSetByName("Default");
        $global = $product->global();
        $global->setName("One Day Fly");
        $global->setPrice('0.01');
        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertEquals([], $product->getErrors());

        // check count
        $newProductCount = $db->fetchSingleCell("
            SELECT COUNT(*) FROM catalog_product_entity
        ");

        $this->assertEquals($productCount + 1, $newProductCount);

        // remove product

        $deleter->deleteProductsByIds([$product->id]);

        // check count
        $newProductCount = $db->fetchSingleCell("
            SELECT COUNT(*) FROM catalog_product_entity
        ");

        $this->assertEquals($productCount, $newProductCount);

        // add product again
        $product->id = null;
        $importer->importSimpleProduct($product);
        $importer->flush();

        $this->assertEquals([], $product->getErrors());

        // check count
        $newProductCount = $db->fetchSingleCell("
            SELECT COUNT(*) FROM catalog_product_entity
        ");

        $this->assertEquals($productCount + 1, $newProductCount);

        // remove product

        $deleter->deleteProductsBySkus([$product->getSku()]);

        // check count
        $newProductCount = $db->fetchSingleCell("
            SELECT COUNT(*) FROM catalog_product_entity
        ");

        $this->assertEquals($productCount, $newProductCount);
    }
}