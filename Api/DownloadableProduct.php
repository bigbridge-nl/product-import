<?php

namespace BigBridge\ProductImport\Api;

/**
 * @author Patrick van Bergen
 */
class DownloadableProduct extends SimpleProduct
{
    /** @var DownloadLink[] */
    protected $downloadLinks = [];

    /** @var DownloadSample[] */
    protected $downloadSamples = [];

    public function getType()
    {
        return 'downloadable';
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
    public function storeView(string $storeViewCode) {
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
    public function global() {
        return $this->storeViews[self::GLOBAL_STORE_VIEW_CODE];
    }

    public function addDownloadLink(string $fileOrUrl, int $numberOfDownloads, bool $isShareable, string $sampleLink = '')
    {
        $link = new DownloadLink($fileOrUrl, $numberOfDownloads, $isShareable, $sampleLink);
        $this->downloadLinks[] = $link;
        return $link;
    }

    public function addDownloadSample(string $fileOrUrl)
    {
        $sample = new DownloadSample($fileOrUrl);
        $this->downloadSamples[] = $sample;
        return $sample;
    }

    /**
     * @return DownloadLink[]
     */
    public function getDownloadLinks(): array
    {
        return $this->downloadLinks;
    }

    /**
     * @return DownloadSample[]
     */
    public function getDownloadSamples(): array
    {
        return $this->downloadSamples;
    }
}