<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\Storage\UrlRewriteStorage;

/**
 * @author Patrick van Bergen
 */
class UrlRewriteUpdater
{
    /** @var ImporterFactory */
    protected $importerFactory;

    /** @var UrlRewriteStorage */
    protected $urlRewriteStorage;

    /** @var MetaData */
    protected $metaData;

    public function __construct(
        MetaData $metaData,
        ImporterFactory $importerFactory,
        UrlRewriteStorage $urlRewriteStorage
    )
    {
        $this->importerFactory = $importerFactory;
        $this->urlRewriteStorage = $urlRewriteStorage;
        $this->metaData = $metaData;
    }

    public function updateUrlRewrites(array $storeViewCodes)
    {
#todo
        $productIds = []; // from Information
        $storeViewIds = []; // from metadata

        $this->urlRewriteStorage->updateRewrites($productIds, $storeViewIds);
    }
}