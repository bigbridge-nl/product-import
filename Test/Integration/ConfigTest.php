<?php

namespace BigBridge\ProductImport\Test\Integration;

use Magento\Framework\App\ObjectManager;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\ImporterFactory;

/**
 * @author Patrick van Bergen
 */
class ConfigTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ImporterFactory */
    private static $factory;

    public static function setUpBeforeClass()
    {
        // include Magento
        require_once __DIR__ . '/../../../../../index.php';

        /** @var ImporterFactory $factory */
        self::$factory = ObjectManager::getInstance()->get(ImporterFactory::class);
    }

    public function testConfig()
    {
        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price'];

        list($importer, $error) = self::$factory->createImporter($config);

        $this->assertNotNull($importer);
        $this->assertEquals("", $error);

        // --------------------

        $config = new ImportConfig();
        $config->eavAttributes = null;

        list($importer, $error) = self::$factory->createImporter($config);

        $this->assertNull($importer);
        $this->assertEquals("config: eavAttributes is not an array", $error);

        // --------------------

        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price', 0.1];

        list($importer, $error) = self::$factory->createImporter($config);

        $this->assertNull($importer);
        $this->assertEquals("config: eavAttributes should be strings", $error);

        // --------------------

        $config = new ImportConfig();
        $config->eavAttributes = ['name', 'price', 'shrdlu', 'sasquatch'];

        list($importer, $error) = self::$factory->createImporter($config);

        $this->assertNull($importer);
        $this->assertEquals("config: eavAttributes: not an eav attribute: shrdlu, sasquatch", $error);
    }
}