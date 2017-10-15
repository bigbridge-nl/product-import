<?php

namespace BigBridge\ProductImport\Model;

use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * This class converts human readable attribute value names to their database ids.
 *
 * @author Patrick van Bergen
 */
class NameConverter
{
    const NOT_FOUND = '-- not found --';

    /** @var MetaData */
    protected $metaData;

    /** @var  ImportConfig */
    protected $config;

    /** @var array  */
    protected $map;

    public function __construct(ImportConfig $config, MetaData $metaData)
    {
        $this->config = $config;
        $this->metaData = $metaData;

        $this->map = $this->loadNameToIdMap();
    }

    /**
     * @param string $attributeCode
     * @param string $attributeValue
     * @return int|string
     */
    public function convertNameToId(string $attributeCode, string $attributeName)
    {
        if (array_key_exists($attributeCode, $this->map)) {
            $names = $this->map[$attributeCode];
            if (array_key_exists($attributeName, $names)) {
                return $names[$attributeName];
            }
        }

        return self::NOT_FOUND;
    }

    protected function loadNameToIdMap()
    {
        $map = [];

        $map['attribute_set_id'] = $this->metaData->attributeSetMap;
        $map['store_view_id'] = $this->metaData->storeViewMap;

        return $map;
    }
}