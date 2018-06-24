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
    public function updateUrlRewrites(array $storeViewCodes)
    {
        $storeViewIds = $this->metaData->getStoreViewIds($storeViewCodes);

        $i = 0;
        while ($productIds = $this->information->getLimitedProductIds($i, self::BUNCH_SIZE)) {
            $this->urlRewriteStorage->updateRewrites($productIds, $storeViewIds);
            $i += self::BUNCH_SIZE;
#todo: not here
echo "\r" . $i;

        }
    }
}