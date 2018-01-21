<?php

namespace BigBridge\ProductImport\Api;

/**
 * @author Patrick van Bergen
 */
class DownloadLink
{
    /** @var string */
    protected $fileOrUrl;

    /** @var int */
    protected $numberOfDownloads;

    /**  @var bool */
    protected $isShareable;

    /**  @var string */
    protected $sampleLink;

    public function __construct(string $fileOrUrl, int $numberOfDownloads, bool $isShareable, string $sampleLink = null)
    {
        $this->fileOrUrl = $fileOrUrl;
        $this->numberOfDownloads = $numberOfDownloads;
        $this->isShareable = $isShareable;
        $this->sampleLink = $sampleLink;
    }
}