<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Model\Resource\Resolver\NameToUrlKeyConverter;
use BigBridge\ProductImport\Model\Resource\Resolver\ReferenceResolver;

/**
 * @author Patrick van Bergen
 */
class ReferenceResolverTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /** @var  ImporterFactory */
    private static $factory;

    public static function setUpBeforeClass(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var ImporterFactory $factory */
        self::$factory = $objectManager->get(ImporterFactory::class);
    }

    /**
     * @throws \Exception
     */
    public function testReferenceResolver()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $config = new ImportConfig();

        /** @var ReferenceResolver $resolver */
        $resolver = $objectManager->get(ReferenceResolver::class);

        $tests = [

            // category_ids

            // plain
            [['category_ids' => ["T-shirts", "Printed Clothing"]], true, ""],

            // attribute_set_id

            // plain
            [['attribute_set_id' => "Default"], true, ""],
            // corrupt
            [['attribute_set_id' => "Winograd"], false, "attribute set name not found: Winograd"],

            // tax_class_id

            // plain
            [['tax_class_id' => "Taxable Goods"], true, ""],
            // corrupt
            [['tax_class_id' => "Codd"], false, "tax class name not found: Codd"],

            // web site ids

            // plain
            [['website_ids' => ["base"]], true, ""],
            // corrupt
            [['website_ids' => ["Shopaholic"]], false, "website code not found: Shopaholic"],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct("big-blue-box");
            $product->setAttributeSetId(4);

            $global = $product->global();
            $global->setName("Big Blue Box");
            $global->setPrice("123.00");

            foreach ($test[0] as $fieldName => $fieldValue) {

                if ($fieldName == 'tax_class_id') {
                    $product->global()->setTaxClassName($fieldValue);
                } elseif ($fieldName == 'category_ids') {
                    $product->addCategoriesByGlobalName($fieldValue);
                } elseif ($fieldName == 'attribute_set_id') {
                    $product->setAttributeSetByName($fieldValue);
                } elseif ($fieldName == 'website_ids') {
                    $product->setWebsitesByCode($fieldValue);
                }
            }

            $resolver->resolveExternalReferences([$product], $config);
            $this->assertEquals($test[2], implode('; ', $product->getErrors()));
            $this->assertEquals($test[1], $product->isOk());

        }
    }

    /**
     * @throws \Exception
     */
    public function testStoreViewResolver()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $config = new ImportConfig();

        /** @var ReferenceResolver $resolver */
        $resolver = $objectManager->get(ReferenceResolver::class);

        $tests = [

            // plain
            ["admin", true, ""],
            // corrupt
            ["Mueller", false, "store view code not found: Mueller"],
        ];

        foreach ($tests as $test) {

            $product = new SimpleProduct("big-blue-box");
            $product->setAttributeSetId(4);

            $global = $product->global();
            $global->setName("Big Blue Box");
            $global->setPrice("123.00");

            $product->storeView($test[0]);

            $resolver->resolveExternalReferences([$product], $config);
            $this->assertEquals($test[2], implode('; ', $product->getErrors()));
            $this->assertEquals($test[1], $product->isOk());

        }
    }

    public function testNameToUrlKeyConverter()
    {
        $converter = new NameToUrlKeyConverter();

        $this->assertSame("computers", $converter->createUrlKeyFromName("Computers"));
        $this->assertSame("computers-software", $converter->createUrlKeyFromName("Computers & Software"));
        $this->assertSame("un-velocipede", $converter->createUrlKeyFromName("Un vélocipède"));
        $this->assertSame("500", $converter->createUrlKeyFromName("€ 500"));
        $this->assertSame("plastic-vorken-deluxe-in-dispenserbox-6-boxen-a-250-st-ds", $converter->createUrlKeyFromName("Plastic Vorken DeLuxe in dispenserbox - 6 boxen à 250 st/ds"));
    }
}
