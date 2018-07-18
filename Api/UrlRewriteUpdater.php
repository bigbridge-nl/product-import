<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\Storage\UrlRewriteStorage;

/**
 * @author Patrick van Bergen
 */
class UrlRewriteUpdater
{
    const BUNCH_SIZE = 1000;

    /** @var ImporterFactory */
    protected $importerFactory;

    /** @var UrlRewriteStorage */
    protected $urlRewriteStorage;

    /** @var MetaData */
    protected $metaData;

    /** @var Information */
    protected $information;

    public function __construct(
        MetaData $metaData,
        ImporterFactory $importerFactory,
        UrlRewriteStorage $urlRewriteStorage,
        Information $information
    )
    {
        $this->importerFactory = $importerFactory;
        $this->urlRewriteStorage = $urlRewriteStorage;
        $this->metaData = $metaData;
        $this->information = $information;
    }

    /**
     * @param array $storeViewCodes
     * @throws \Exception
     */
    public function updateUrlRewrites(array $storeViewCodes, UrlRewriteUpdateLogger $logger, bool $keepRedirects)
    {
        $storeViewIds = $this->metaData->getStoreViewIds($storeViewCodes);
        $productIds = $this->information->getProductIds();

        $i = 0;
        while ($chunkedIds = array_slice($productIds, $i, self::BUNCH_SIZE)) {
            $this->urlRewriteStorage->updateRewritesByProductIds($chunkedIds, $storeViewIds, $keepRedirects);
            $i += self::BUNCH_SIZE;

            $logger->info($i);
        }
    }
}