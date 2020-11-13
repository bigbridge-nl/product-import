<?php

namespace BigBridge\ProductImport\Test\Unit;

use BigBridge\ProductImport\Model\Resource\Serialize\JsonValueSerializer;
use BigBridge\ProductImport\Model\Resource\Serialize\SerializeValueSerializer;

/**
 * @author Patrick van Bergen
 */
class ValueSerializerTest extends \PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass(): void
    {
        // include Magento auto-loading
        require_once __DIR__ . '/../../../../../app/autoload.php';
    }

    public function testSerializeValueSerializer()
    {
        $ser = new SerializeValueSerializer();

        $this->assertEquals(null, $ser->serialize(null));
        $this->assertEquals(serialize(['category_id' => "13"]), $ser->serialize(['category_id' => "13"]));

        $this->assertEquals("13", $ser->extract(serialize(['category_id' => "13"]), 'category_id'));
        $this->assertEquals(null, $ser->extract(serialize(['category_id' => "13"]), 'attribute_id'));
        $this->assertEquals(null, $ser->extract(serialize([]), 'attribute_id'));
        $this->assertEquals(null, $ser->extract(null, 'category_id'));
    }

    public function testJsonValueSerializer()
    {
        $ser = new JsonValueSerializer();

        $this->assertEquals(null, $ser->serialize(null));
        $this->assertEquals(json_encode(['category_id' => "13"]), $ser->serialize(['category_id' => "13"]));

        $this->assertEquals("13", $ser->extract(json_encode(['category_id' => "13"]), 'category_id'));
        $this->assertEquals(null, $ser->extract(json_encode(['category_id' => "13"]), 'attribute_id'));
        $this->assertEquals(null, $ser->extract(json_encode([]), 'attribute_id'));
        $this->assertEquals(null, $ser->extract(null, 'category_id'));
    }
}
