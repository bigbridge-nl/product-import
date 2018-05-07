<?php

namespace BigBridge\ProductImport\Test\Integration;

use Magento\Framework\App\ObjectManager;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use Exception;

/**
 * @author Patrick van Bergen
 */
class ConfigTest extends \PHPUnit\Framework\TestCase
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
        $importer = null;

        try {
            $importer = self::$factory->createImporter($config);
        } catch (Exception $exception) {
            $this->assertTrue(false);
        }

        $this->assertNotNull($importer);

        // ---

        $importer = null;
        $config = new ImportConfig();
        $config->batchSize = 0;

        try {
            $importer = self::$factory->createImporter($config);
        } catch (Exception $exception) {
            $this->assertEquals("config: batchSize should be 1 or more", $exception->getMessage());
        }

        $this->assertNull($importer);

        // ---

        $importer = null;
        $config = new ImportConfig();
        $config->batchSize = "1000";

        try {
            $importer = self::$factory->createImporter($config);
        } catch (Exception $exception) {
            $this->assertEquals("config: batchSize is not an integer", $exception->getMessage());
        }

        $this->assertNull($importer);

        // ---

        $importer = null;
        $config = new ImportConfig();
        $config->resultCallbacks = function() {};

        try {
            $importer = self::$factory->createImporter($config);
        } catch (Exception $exception) {
            $this->assertEquals("config: resultCallbacks should be an array of functions", $exception->getMessage());
        }

        $this->assertNull($importer);

        // ---

        $config = new ImportConfig();

        try {
            self::$factory->createImporter($config);
        } catch (Exception $exception) {
            $this->assertTrue(false);
        }


        // $config has copied, the original is unchanged
        $this->assertEquals(null, $config->magentoVersion);

        // ---

        $importer = null;
        $config = new ImportConfig();
        $config->magentoVersion = '2';

        try {
            $importer = self::$factory->createImporter($config);
        } catch (Exception $exception) {
            $this->assertEquals("config: invalid Magento version number", $exception->getMessage());
        }

        $this->assertNull($importer);

    }
}