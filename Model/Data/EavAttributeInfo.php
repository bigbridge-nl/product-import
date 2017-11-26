<?php

namespace BigBridge\ProductImport\Model\Data;

/**
 * Data from eav_attribute
 *
 * @author Patrick van Bergen
 */
class EavAttributeInfo
{
    /** @var  string */
    public $attributeCode;

    /** @var  int */
    public $attributeId;

    /** @var  bool */
    public $isRequired;

    /** @var string  */
    public $backendType;

    /** @var  string */
    public $frontendInput;

    /** @var array  */
    public $optionValues;

    /** @var  string */
    public $tableName;

    public function __construct(string $attributeCode, int $attributeId, bool $isRequired, string  $backendType, string $tableName, $frontendInput, array $optionValues)
    {
        $this->attributeCode = $attributeCode;
        $this->attributeId = $attributeId;
        $this->isRequired = $isRequired;
        $this->backendType = $backendType;
        $this->tableName = $tableName;
        $this->frontendInput = $frontendInput;
        $this->optionValues = $optionValues;
    }
}