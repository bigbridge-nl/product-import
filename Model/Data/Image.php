<?php

namespace BigBridge\ProductImport\Model\Data;

/**
 * @author Patrick van Bergen
 */
class Image
{
    /** @var int */
    public $valueId;

    /** @var string */
    protected $imagePath;

    /** @var string  */
    protected $storagePath;

    /** @var bool */
    protected $enabled;

    public function __construct(string $imagePath, bool $enabled)
    {
        $this->imagePath = $imagePath;
        $this->storagePath = $this->createStoragePath($imagePath);

        $this->enabled = $enabled;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getStoragePath()
    {
        return $this->storagePath;
    }

    protected function createStoragePath(string $imagePath)
    {
        $imageBase = basename($imagePath);

        $c1 = $c2 = '_';

        if ($imageBase !== "") {
            if ($imageBase[0] !== ".") {
                $c1 = $imageBase[0];
                if ($imageBase[1] !== ".") {
                    $c2 = $imageBase[1];
                }
            }
        }

        return "/{$c1}/{$c2}/{$imageBase}";
    }
}