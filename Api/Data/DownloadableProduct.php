<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class DownloadableProduct extends SimpleProduct
{
    const TYPE_DOWNLOADABLE = 'downloadable';

    /** @var DownloadLink[]|null */
    protected $downloadLinks = null;

    /** @var DownloadSample[]|null */
    protected $downloadSamples = null;

    public function getType()
    {
        return self::TYPE_DOWNLOADABLE;
    }

    /**
     * @param string $sku
     */
    public function __construct(string $sku)
    {
        parent::__construct($sku);

        $this->storeViews[self::GLOBAL_STORE_VIEW_CODE] = new DownloadableProductStoreView();
    }

    /**
     * @param string $storeViewCode
     * @return DownloadableProductStoreView
     */
    public function storeView(string $storeViewCode)
    {
        $storeViewCode = trim($storeViewCode);
        if (!array_key_exists($storeViewCode, $this->storeViews)) {
            $storeView = new DownloadableProductStoreView();
            $this->storeViews[$storeViewCode] = $storeView;
        } else {
            $storeView = $this->storeViews[$storeViewCode];
        }
        return $storeView;
    }

    /**
     * @return DownloadableProductStoreView
     */
    public function global()
    {
        return $this->storeViews[self::GLOBAL_STORE_VIEW_CODE];
    }

    /**
     * @return DownloadableProductStoreView[]|ProductStoreView[]
     */
    public function getStoreViews()
    {
        return $this->storeViews;
    }

    /**
     * @param DownloadLink[] $links
     */
    public function setDownloadLinks(array $links)
    {
        $this->downloadLinks = $links;
    }

    /**
     * @param DownloadSample[] $samples
     */
    public function setDownloadSamples(array $samples)
    {
        $this->downloadSamples = $samples;
    }

    /**
     * @return DownloadLink[]|null
     */
    public function getDownloadLinks()
    {
        return $this->downloadLinks;
    }

    /**
     * @return DownloadSample[]|null
     */
    public function getDownloadSamples()
    {
        return $this->downloadSamples;
    }
}