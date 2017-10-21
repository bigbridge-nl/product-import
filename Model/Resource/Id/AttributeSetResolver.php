<?php

namespace BigBridge\ProductImport\Model\Resource\Id;

use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class AttributeSetResolver
{
    /** @var MetaData */
    protected $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    public function resolveName(string $name): array
    {
        $error = "";
        $id = null;

        if (!array_key_exists($name, $this->metaData->productAttributeSetMap)) {
            $error = "attribute set not found: " . $name;
        } else {
            $id = $this->metaData->productAttributeSetMap[$name];
        }

        return [$id, $error];
    }
}
