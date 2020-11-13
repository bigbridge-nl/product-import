<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\Data\Weee;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class WeeeTest extends \Magento\TestFramework\TestCase\AbstractController
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
    public function testWeee()
    {
        $errors = [];

        if (empty(self::$metadata->weeeAttributeId)) {
            self::$metadata->weeeAttributeId = $this->createWeeeAttribute();
        }

        $attributeId = self::$metadata->weeeAttributeId;

        $config = new ImportConfig();
        $config->resultCallback = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        // create an object
        $product1 = new SimpleProduct('weee-product-import');
        $product1->setAttributeSetByName("Default");

        $global = $product1->global();
        $global->setName("Weee there we go!");
        $global->setPrice('1.23');

        $product1->setWeees([
            Weee::createWeee('NL', 5.0, 1, Weee::DEFAULT_STATE),
            Weee::createWeee('ES', 3.0, 1, Weee::DEFAULT_STATE)
        ]);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $expected = [
            [1, $product1->id, 'NL', 5.0, 0, $attributeId],
            [1, $product1->id, 'ES', 3.0, 0, $attributeId],
        ];

        $this->assertEquals([], $product1->getErrors());
        $this->assertEquals($expected, $this->getWeeeTaxes($product1->id));

        $product1->setWeees([]);

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $expected = [
        ];

        $this->assertEquals([], $product1->getErrors());
        $this->assertEquals($expected, $this->getWeeeTaxes($product1->id));
    }

    protected function getWeeeTaxes($productId)
    {
        return self::$db->fetchAllNonAssoc("
            SELECT website_id, entity_id, country, value, state, attribute_id
            FROM " . self::$metadata->weeeTable . "
            WHERE entity_id = {$productId}
            ORDER BY value_id
        ");
    }

    protected function createWeeeAttribute()
    {
        // create a multiple select attribute
        self::$db->execute("
            INSERT INTO " . self::$metadata->attributeTable . "
            SET
                entity_type_id = " . self::$metadata->productEntityTypeId . ",
                attribute_code = 'weee_importer',
                frontend_input = 'weee',
                backend_type = 'static'
        ");

        $insertId = self::$db->getLastInsertId();

        self::$db->execute("
            INSERT INTO " . self::$metadata->catalogAttributeTable . "
            SET
                attribute_id = " . $insertId . ",
                is_global = 1
        ");

        self::$metadata->productEavAttributeInfo['weee_importer'] = new EavAttributeInfo('weee_importer', $insertId, false, 'static', 'weee_tax', 'weee', 1);

        return $insertId;
    }
}
