<?php

namespace BigBridge\ProductImport\Api;

/**
 * @author Patrick van Bergen
 */
class DownloadableProduct extends SimpleProduct
{
    /** @var DownloadLink[] */
    protected $links = [];

    /** @var DownloadSample[] */
    protected $samples = [];

    public function getType()
    {
        return 'downloadable';
    }

    public function addDownloadLink(string $fileOrUrl, int $numberOfDownloads, bool $isShareable, string $sampleLink = null)
    {
        $link = new DownloadLink($fileOrUrl, $numberOfDownloads, $isShareable, $sampleLink);
        $this->links[] = $link;
        return $link;
    }

    public function addDownloadSample(string $fileOrUrl)
    {
        $sample = new DownloadSample($fileOrUrl);
        $this->samples[] = $sample;
        return $sample;
    }
}