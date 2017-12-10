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

    /** @var string Image path if their are no conflicting image /d/u/duck.jpg */
    protected $defaultStoragePath;

    /** @var string Calculated image path where the file is really located (i.e.  /d/u/duck_2.jpg) */
    protected $actualStoragePath;

    /** @var bool Is this image in use (if not, it will not appear in frontend and backend. */
    protected $enabled;

    public function __construct(string $imagePath)
    {
        $this->imagePath = $imagePath;
        $this->enabled = true;

        $this->defaultStoragePath = $this->createStoragePath($imagePath);
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    /**
     * Disabling an image will make it invisible on the frontend and even in the backend.
     * It has no use, it is added merely for completeness.
     */
    public function disable()
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getDefaultStoragePath()
    {
        return $this->defaultStoragePath;
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

    /**
     * @return string
     */
    public function getActualStoragePath(): string
    {
        return $this->actualStoragePath;
    }

    /**
     * @param string $actualStoragePath
     */
    public function setActualStoragePath(string $actualStoragePath)
    {
        $this->actualStoragePath = $actualStoragePath;
    }
}