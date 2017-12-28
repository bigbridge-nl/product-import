<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

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

    public function resolveCodes(array $codes): array
    {
        $notFound = [];
        $ids = [];
        $error = "";

        foreach ($codes as $code) {

            if (!array_key_exists($code, $this->metaData->websiteMap)) {
                $notFound[] = $code;
            } else {
                $ids[] = $this->metaData->websiteMap[$code];
            }
        }

        if (!empty($notFound)) {
            $error = "website code not found: " . implode(', ', $notFound);
        }

        return [$ids, $error];
    }

    public function resolveCode(string $code): array
    {
        $id = null;
        $error = "";

        if (!array_key_exists($code, $this->metaData->websiteMap)) {
            $error = "website code not found: " . $code;
        } else {
            $id = $this->metaData->websiteMap[$code];
        }

        return [$id, $error];
    }
}