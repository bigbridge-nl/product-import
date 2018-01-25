<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Model\Data\DownloadLinkInformation;
use BigBridge\ProductImport\Model\Data\DownloadSampleInformation;

/**
 * @author Patrick van Bergen
 */
class DownloadableProductStoreView extends ProductStoreView
{
    const ATTR_LINKS_PURCHASED_SEPARATELY = 'links_purchased_separately';
    const ATTR_LINKS_TITLE = 'links_title';
    const ATTR_SAMPLES_TITLE = 'samples_title';

    /** @var DownloadLinkInformation[] */
    protected $downloadLinkInformations;

    /** @var DownloadSampleInformation[] */
    protected $downloadSampleInformations;

    /**
     * Can each of the links from the downloadable product be purchased separately?
     *
     * @param bool $separately
     */
    public function setLinksPurchasedSeparately(bool $separately)
    {
        $this->attributes[self::ATTR_LINKS_PURCHASED_SEPARATELY] = $separately;
    }

    /**
     * The caption of the links section of a downloadable product.
     *
     * @param string $linksTitle
     */
    public function setLinksTitle(string $linksTitle)
    {
        $this->attributes[self::ATTR_LINKS_TITLE] = trim($linksTitle);
    }

    /**
     * The caption of the samples section of a downloadable product.
     *
     * @param string $samplesTitle
     */
    public function setSamplesTitle(string $samplesTitle)
    {
        $this->attributes[self::ATTR_SAMPLES_TITLE] = trim($samplesTitle);
    }

    public function setDownloadLinkInformation(DownloadLink $downloadLink, string $title, string $price)
    {
        $this->downloadLinkInformations[] = new DownloadLinkInformation($downloadLink, $title, $price);
    }

    /**
     * @return DownloadLinkInformation[]
     */
    public function getDownloadLinkInformations()
    {
        return $this->downloadLinkInformations;
    }

    public function setDownloadSampleInformation(DownloadSample $downloadSample, string $title)
    {
        $this->downloadSampleInformations[] = new DownloadSampleInformation($downloadSample, $title);
    }

    /**
     * @return DownloadSampleInformation[]
     */
    public function getDownloadSampleInformations()
    {
        return $this->downloadSampleInformations;
    }
}