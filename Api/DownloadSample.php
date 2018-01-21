<?php

namespace BigBridge\ProductImport\Api;

/**
 * @author Patrick van Bergen
 */
class DownloadSample
{
    /** @var string */
    protected $fileOrUrl;

    public function __construct(string $fileOrUrl)
    {
        $this->fileOrUrl = $fileOrUrl;
    }
}