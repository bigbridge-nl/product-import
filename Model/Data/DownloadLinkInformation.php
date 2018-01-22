<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Api\DownloadLink;

/**
 * @author Patrick van Bergen
 */
class DownloadLinkInformation
{
    /**  @var DownloadLink */
    protected $downloadLink;

    /** @var string */
    protected $title;

    /** @var string */
    protected $price;

    public function __construct(DownloadLink $downloadLink, string $title, string $price)
    {
        $this->title = trim($title);
        $this->price = trim($price);
        $this->downloadLink = trim($downloadLink);
    }

    /**
     * @return DownloadLink
     */
    public function getDownloadLink(): DownloadLink
    {
        return $this->downloadLink;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getPrice(): string
    {
        return $this->price;
    }
}