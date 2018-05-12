<?php

namespace BigBridge\ProductImport\Model\Data;

use BigBridge\ProductImport\Api\Data\CustomOption;

/**
 * @author Patrick van Bergen
 */
class CustomOptionTitle
{
    /** @var CustomOption */
    protected $customOption;

    /** @var string */
    protected $title;

    public function __construct(CustomOption $customOption, string $title)
    {
        $this->customOption = $customOption;
        $this->title = trim($title);
    }

    /**
     * @return CustomOption
     */
    public function getCustomOption(): CustomOption
    {
        return $this->customOption;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }
}