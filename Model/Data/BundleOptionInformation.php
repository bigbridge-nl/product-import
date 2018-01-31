<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Api\Data\BundleProductOption;

/**
 * @author Patrick van Bergen
 */
class BundleOptionInformation
{
    /** @var BundleProductOption */
    protected $option;

    /** @var string */
    protected $title;

    public function __construct(BundleProductOption $option, string $title)
    {
        $this->option = $option;
        $this->title = $title;
    }

    /**
     * @return BundleProductOption
     */
    public function getOption(): BundleProductOption
    {
        return $this->option;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }
}