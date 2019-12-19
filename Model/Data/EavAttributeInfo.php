<?php

namespace BigBridge\ProductImport\Model\Data;

/**
 * Data from eav_attribute
 *
 * @author Patrick van Bergen
 */
class EavAttributeInfo
{
    const SCOPE_STORE_VIEW = 0;
    const SCOPE_GLOBAL = 1;
    const SCOPE_WEBSITE = 2;

    const FRONTEND_SELECT = 'select';
    const FRONTEND_MEDIA_IMAGE = 'media_image';

    const TYPE_DATETIME = 'datetime';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_INTEGER = 'int';
    const TYPE_VARCHAR = 'varchar';
    const TYPE_TEXT = 'text';

    /** @var  string */
    public $attributeCode;

    /** @var  int */
    public $attributeId;

    /** @var  bool */
    public $isRequired;

    /** @var string */
    public $backendType;

    /** @var  string */
    public $frontendInput;

    /** @var  string */
    public $tableName;

    /** @var int */
    public $scope;

    public function __construct(string $attributeCode, int $attributeId, bool $isRequired, string $backendType, string $tableName, $frontendInput, int $scope)
    {
        $this->attributeCode = $attributeCode;
        $this->attributeId = $attributeId;
        $this->isRequired = $isRequired;
        $this->backendType = $backendType;
        $this->tableName = $tableName;
        $this->frontendInput = $frontendInput;
        $this->scope = $scope;
    }

    /**
     * This flag determines if this field contains free-form text. Text that may just be an empty string.
     *
     * @return bool
     */
    public function isTextual()
    {
        return
            in_array($this->backendType, [self::TYPE_TEXT, self::TYPE_VARCHAR])
            // a multiselect field is stored in a varchar table, as a comma separated list of ids
            && ($this->frontendInput != "multiselect");
    }
}
