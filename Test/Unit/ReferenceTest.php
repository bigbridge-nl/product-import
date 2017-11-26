<?php

namespace BigBridge\ProductImport\Test\Unit;

use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;

/**
 * @author Patrick van Bergen
 */
class ReferenceTest extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        // include Magento auto-loading
        require_once __DIR__ . '/../../../../../app/autoload.php';
    }

    public function testReference()
    {
        $ref = new Reference("unicorns ");
        $this->assertEquals("unicorns", $ref->name);
    }

    public function testReferences()
    {
        $ref = new References([" boys", "girls ", " other "]);
        $this->assertEquals(["boys", "girls", "other"], $ref->names);
    }
}