<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Resource\MetaData;

class WeeeResolver
{
    /** @var MetaData */
    protected $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    public function resolveWeeeAttributeId(): string
    {
        if ($this->metaData->weeeAttributeId === null) {
            return "weee attribute not found";
        }

        return "";
    }

}
