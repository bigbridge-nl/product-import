<?php

namespace BigBridge\ProductImport\Model\Data;

/**
 * @author Patrick van Bergen
 */
class Image
{
    /** @var int Database gallery media image id */
    public $valueId;

    /** @var string Image path or url given by the user */
    protected $imagePath;

    /** @var string Relative image path if there are no conflicting image /d/u/duck.jpg */
    protected $defaultStoragePath;

    /** @var string Calculated relative image path where the file is really located (i.e.  /d/u/duck_2.jpg) */
    protected $actualStoragePath;

    /** @var string Absolute path. In the validation process the image is located here, temporarily */
    protected $temporaryStoragePath;

    /** @var bool Is this image in use (if not, it will not appear in frontend and backend. */
    protected $enabled;

    public function __construct(string $imagePath)
    {
        $this->imagePath = trim($imagePath);
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

    public function createStoragePath(string $imagePath)
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

    /**
     * @return string
     */
    public function getTemporaryStoragePath(): string
    {
        return $this->temporaryStoragePath;
    }

    /**
     * @param string $temporaryStoragePath
     */
    public function setTemporaryStoragePath(string $temporaryStoragePath)
    {
        $this->temporaryStoragePath = $temporaryStoragePath;
    }
}