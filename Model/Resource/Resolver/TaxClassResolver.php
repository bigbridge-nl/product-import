<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class TaxClassResolver
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

        if (!array_key_exists($name, $this->metaData->taxClassMap)) {
            $error = "tax class name not found: " . $name;
        } else {
            $id = $this->metaData->taxClassMap[$name];
        }

        return [$id, $error];
    }
}