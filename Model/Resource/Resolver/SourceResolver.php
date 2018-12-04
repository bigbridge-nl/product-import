<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * Checks if a name is defined as an msi source code
 *
 * @author Patrick van Bergen
 */
class SourceResolver
{
    /** @var MetaData */
    protected $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    public function resolveName(string $name): string
    {
        $error = "";

        if (!array_key_exists($name, $this->metaData->sourceCodeMap)) {
            $error = "source code not found: " . $name;
        }

        return $error;
    }
}