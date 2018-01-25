<?php

namespace BigBridge\ProductImport\Api\Data;

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
    protected $sampleFileOrUrl;

    /** @var int */
    protected $id = null;

    /** @var string */
    protected $temporaryStoragePathLink;

    /** @var string */
    protected $temporaryStoragePathSample;

    public function __construct(string $fileOrUrl, int $numberOfDownloads, bool $isShareable, string $sampleFileOrUrl = '')
    {
        $this->fileOrUrl = $fileOrUrl;
        $this->numberOfDownloads = $numberOfDownloads;
        $this->isShareable = $isShareable;
        $this->sampleFileOrUrl = $sampleFileOrUrl;
    }

    /**
     * @return string
     */
    public function getFileOrUrl(): string
    {
        return $this->fileOrUrl;
    }

    /**
     * @return int
     */
    public function getNumberOfDownloads(): int
    {
        return $this->numberOfDownloads;
    }

    /**
     * @return bool
     */
    public function isShareable(): bool
    {
        return $this->isShareable;
    }

    /**
     * @return string
     */
    public function getSampleFileOrUrl(): string
    {
        return $this->sampleFileOrUrl;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTemporaryStoragePathLink()
    {
        return $this->temporaryStoragePathLink;
    }

    /**
     * @param string $temporaryStoragePathLink
     */
    public function setTemporaryStoragePathLink($temporaryStoragePathLink)
    {
        $this->temporaryStoragePathLink = $temporaryStoragePathLink;
    }

    /**
     * @return string
     */
    public function getTemporaryStoragePathSample()
    {
        return $this->temporaryStoragePathSample;
    }

    /**
     * @param string $temporaryStoragePathSample
     */
    public function setTemporaryStoragePathSample($temporaryStoragePathSample)
    {
        $this->temporaryStoragePathSample = $temporaryStoragePathSample;
    }
}