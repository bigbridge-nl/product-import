<?php

namespace BigBridge\ProductImport\Model\Resource\Reference;

use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class WebsiteResolver
{
    /** @var MetaData */
    protected $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    public function resolveNames(array $names): array
    {
        $notFound = [];
        $ids = [];
        $error = "";

        foreach ($names as $name) {

            if (!array_key_exists($name, $this->metaData->websiteMap)) {
                $notFound[] = $name;
            } else {
                $ids[] = $this->metaData->websiteMap[$name];
            }
        }

        if (!empty($notFound)) {
            $error = "website code not found: " . implode(', ', $notFound);
        }

        return [$ids, $error];
    }
}