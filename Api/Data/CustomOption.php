<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class CustomOption
{
    /** @var string */
    protected $type;

    /** @var bool */
    protected $required;

    /** @var string|null */
    protected $sku;

    /** @var int */
    protected $maxCharacters;

    /** @var string|null */
    protected $fileExtensions;

    /** @var int */
    protected $imageSizeX;

    /** @var int */
    protected $imageSizeY;

    /** @var string[] */
    protected $valueSkus;

    /** @var int|null */
    protected $optionId;

    /** @var int */
    protected static $keyGen = 0;

    /** @var int */
    protected $uniqueKey;

    public function __construct(string $type, bool $required, $sku, int $maxCharacters, $fileExtensions, int $imageSizeX, int $imageSizeY, array $valueSkus)
    {
        $this->type = $type;
        $this->required = $required;
        $this->sku = $sku === "" ? null : $sku;
        $this->maxCharacters = $maxCharacters;
        $this->fileExtensions = $fileExtensions;
        $this->imageSizeX = $imageSizeX;
        $this->imageSizeY = $imageSizeY;
        $this->setValueSkus($valueSkus);

        $this->uniqueKey = ++self::$keyGen;
    }

    public function getUniqueKey()
    {
        return $this->uniqueKey;
    }

    /**
     * @param string[] $valueSkus
     */
    public function setValueSkus(array $valueSkus)
    {
        $skus = [];

        foreach ($valueSkus as $sku) {
            if ($sku === null) {
                $skus[] = null;
            } elseif ($sku === "") {
                $skus[] = null;
            } else {
                $skus[] = trim($sku);
            }
        }

        $this->valueSkus = $skus;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return string|null
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @return int
     */
    public function getMaxCharacters(): int
    {
        return $this->maxCharacters;
    }

    /**
     * @return string|null
     */
    public function getFileExtensions()
    {
        return $this->fileExtensions;
    }

    /**
     * @return int
     */
    public function getImageSizeX(): int
    {
        return $this->imageSizeX;
    }

    /**
     * @return int
     */
    public function getImageSizeY(): int
    {
        return $this->imageSizeY;
    }

    public static function createCustomOptionTextField($sku, bool $required, int $maxCharacters)
    {
        return new CustomOption('field', $required, trim($sku), $maxCharacters, null, 0, 0, []);
    }

    public static function createCustomOptionTextArea($sku, bool $required, int $maxCharacters)
    {
        return new CustomOption('area', $required, trim($sku), $maxCharacters, null, 0, 0, []);
    }

    /**
     * @param string $sku
     * @param bool $required
     * @param string $fileExtensions For example: "jpg jpeg"
     * @param int $maxWidth Number of pixels (0 = no limit)
     * @param int $maxHeight Number of pixels (0 = no limit)
     * @return CustomOption
     */
    public static function createCustomOptionFile($sku, bool $required, string $fileExtensions, int $maxWidth = 0, int $maxHeight = 0)
    {
        return new CustomOption('file', $required, trim($sku), 0, trim($fileExtensions), $maxWidth, $maxHeight, []);
    }

    public static function createCustomOptionDate($sku, bool $required)
    {
        return new CustomOption('date', $required, trim($sku), 0, null, 0, 0, []);
    }

    public static function createCustomOptionDateTime($sku, bool $required)
    {
        return new CustomOption('date_time', $required, trim($sku), 0, null, 0, 0, []);
    }

    public static function createCustomOptionTime($sku, bool $required)
    {
        return new CustomOption('time', $required, trim($sku), 0, null, 0, 0, []);
    }

    public static function createCustomOptionDropDown(bool $required, array $valueSkus)
    {
        return new CustomOption('drop_down', $required, null, 0, null, 0, 0, $valueSkus);
    }

    public static function createCustomOptionRadioButtons(bool $required, array $valueSkus)
    {
        return new CustomOption('radio', $required, null, 0, null, 0, 0, $valueSkus);
    }

    public static function createCustomOptionCheckboxGroup(bool $required, array $valueSkus)
    {
        return new CustomOption('checkbox', $required, null, 0, null, 0, 0, $valueSkus);
    }

    public static function createCustomOptionMultipleSelect(bool $required, array $valueSkus)
    {
        return new CustomOption('multiple', $required, null, 0, null, 0, 0, $valueSkus);
    }

    /**
     * @return int|null
     */
    public function getOptionId()
    {
        return $this->optionId;
    }

    /**
     * @param int $optionId
     */
    public function setOptionId(int $optionId)
    {
        $this->optionId = $optionId;
    }

    /**
     * @return string[]
     */
    public function getValues(): array
    {
        return $this->valueSkus;
    }
}