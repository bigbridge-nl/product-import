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

    /** @var int */
    public $typeId;

    /** @var int */
    public $positionAttributeId;

    public function __construct(int $typeId, int $positionAttributeId)
    {
        $this->typeId = $typeId;
        $this->positionAttributeId = $positionAttributeId;
    }

}