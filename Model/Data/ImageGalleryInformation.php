<?php

namespace BigBridge\ProductImport\Model\Data;

/**
 * @author Patrick van Bergen
 */
class ImageGalleryInformation
{
    /** @var Image*/
    protected $image;

    /**@var string */
    protected $label;

    /** @var int */
    protected $position;

    /** @var bool */
    protected $enabled;

    public function __construct(Image $image, string $label, int $position, bool $enabled)
    {
        $this->image = $image;
        $this->label = $label;
        $this->position = $position;
        $this->enabled = $enabled;
    }

    /**
     * @return Image
     */
    public function getImage(): Image
    {
        return $this->image;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}