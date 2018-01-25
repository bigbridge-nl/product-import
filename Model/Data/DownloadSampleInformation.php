<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Api\Data\DownloadSample;

/**
 * @author Patrick van Bergen
 */
class DownloadSampleInformation
{
    /**  @var DownloadSample */
    protected $downloadSample;

    /** @var string */
    protected $title;

    public function __construct(DownloadSample $downloadSample, string $title)
    {
        $this->downloadSample = $downloadSample;
        $this->title = trim($title);
    }

    /**
     * @return DownloadSample
     */
    public function getDownloadSample(): DownloadSample
    {
        return $this->downloadSample;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }
}