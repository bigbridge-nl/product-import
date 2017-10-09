<?php

namespace BigBridge\ProductImport\Model\Resource;

/**
 * @author Patrick van Bergen
 */
class AttributeInfo
{
    /** @var  string */
    public $attributeCode;

    /** @var  int */
    public $attributeId;

    /** @var  bool */
    public $isRequired;

    /** @var  string */
    public $tableName;

    public function __construct(string $attributeCode, int $attributeId, bool $isRequired, string $tableName)
    {
        $this->attributeCode = $attributeCode;
        $this->attributeId = $attributeId;
        $this->isRequired = $isRequired;
        $this->tableName = $tableName;
    }
}