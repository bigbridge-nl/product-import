<?php

namespace BigBridge\ProductImport\Model\Data;

/**
 * @author Patrick van Bergen
 */
class LinkInfo
{
    const RELATED = 'related';
    const UP_SELL = 'up_sell';
    const CROSS_SELL = 'cross_sell';
    const SUPER = 'super';

    /** @var int */
    public $typeId;

    /** @var int */
    public $positionAttributeId;

    /** @var int */
    public $defaultQuantityAttributeId;

    public function __construct(int $typeId, int $positionAttributeId, ?int $defaultQuantityAttributeId = null)
    {
        $this->typeId = $typeId;
        $this->positionAttributeId = $positionAttributeId;
        $this->defaultQuantityAttributeId = $defaultQuantityAttributeId;
    }

}
