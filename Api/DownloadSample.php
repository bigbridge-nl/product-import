<?php

namespace BigBridge\ProductImport\Api;

/**
 * @author Patrick van Bergen
 */
class DownloadSample
{
    /** @var string */
    protected $fileOrUrl;

    /** @var int */
    protected $id = null;

    public function __construct(string $fileOrUrl)
    {
        $this->fileOrUrl = $fileOrUrl;
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
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }
}